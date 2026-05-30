<?php

namespace App\Livewire\Campanas;

use App\Models\CampanaDestinatario;
use App\Models\CampanaWhatsapp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Informe de campaña')]
class Informe extends Component
{
    use WithPagination;

    public CampanaWhatsapp $campana;
    public string $filtro = 'todos'; // todos | leyeron | respondieron | clicaron | reaccionaron | convirtieron | fallaron
    public string $busqueda = '';

    public function mount(int $id): void
    {
        $this->campana = CampanaWhatsapp::query()
            ->withoutGlobalScopes()
            ->where('id', $id)
            ->firstOrFail();
    }

    public function setFiltro(string $f): void
    {
        $this->filtro = $f;
        $this->resetPage();
    }

    public function getKpisProperty(): array
    {
        $q = CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id);

        $total = (clone $q)->count();
        $enviados = (clone $q)->where('estado', CampanaDestinatario::ESTADO_ENVIADO)->count();
        $entregados = (clone $q)->whereNotNull('entregado_at')->count();
        $leidos = (clone $q)->whereNotNull('leido_at')->count();
        $respondieron = (clone $q)->whereNotNull('respondio_at')->count();
        $clicaron = (clone $q)->whereNotNull('boton_click_at')->count();
        $reaccionaron = (clone $q)->whereNotNull('reaccion_at')->count();
        $convirtieron = (clone $q)->whereNotNull('pedido_id')->count();
        $fallaron = (clone $q)->where('estado', CampanaDestinatario::ESTADO_FALLIDO)->count();

        $pct = fn($n, $base) => $base > 0 ? round(($n / $base) * 100, 1) : null;

        // Detectar campañas anteriores al tracking (no tienen wamids → no podemos cruzar)
        $sinTracking = $enviados > 0 && CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id)
            ->whereNotNull('mensaje_externo_id')
            ->doesntExist();

        return [
            'total'         => $total,
            'enviados'      => $enviados,
            'entregados'    => $entregados,
            'leidos'        => $leidos,
            'respondieron'  => $respondieron,
            'clicaron'      => $clicaron,
            'reaccionaron'  => $reaccionaron,
            'convirtieron' => $convirtieron,
            'fallaron'     => $fallaron,
            'pct_entregados'   => $pct($entregados, $enviados),
            'pct_leidos'       => $pct($leidos, $entregados ?: $enviados),
            'pct_respondieron' => $pct($respondieron, $entregados ?: $enviados),
            'pct_clicaron'     => $pct($clicaron, $entregados ?: $enviados),
            'pct_convirtieron' => $pct($convirtieron, $entregados ?: $enviados),
            'pct_fallaron'    => $pct($fallaron, $total),
            'sin_tracking'    => $sinTracking,
        ];
    }

    /** Botones más clicados (Top N). */
    public function getBotonesProperty(): array
    {
        return CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id)
            ->whereNotNull('boton_click')
            ->selectRaw('boton_click, COUNT(*) as n')
            ->groupBy('boton_click')
            ->orderByDesc('n')
            ->get()
            ->toArray();
    }

    /** Reacciones agregadas por emoji. */
    public function getReaccionesProperty(): array
    {
        return CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id)
            ->whereNotNull('reaccion')
            ->selectRaw('reaccion, COUNT(*) as n')
            ->groupBy('reaccion')
            ->orderByDesc('n')
            ->get()
            ->toArray();
    }

    /** Timeline: actividad por hora del día (envíos / lecturas / respuestas). */
    public function getTimelineProperty(): array
    {
        $base = CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id);

        $serie = function ($columna) use ($base) {
            return (clone $base)
                ->whereNotNull($columna)
                ->selectRaw("DATE_FORMAT($columna, '%Y-%m-%d %H:00') as hora, COUNT(*) as n")
                ->groupBy('hora')
                ->orderBy('hora')
                ->pluck('n', 'hora')
                ->toArray();
        };

        // Unir horas de todas las series para el eje X
        $enviados   = $serie('enviado_at');
        $leidos     = $serie('leido_at');
        $respondio  = $serie('respondio_at');

        $horas = collect()
            ->merge(array_keys($enviados))
            ->merge(array_keys($leidos))
            ->merge(array_keys($respondio))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'horas'      => $horas,
            'enviados'   => array_map(fn($h) => $enviados[$h]  ?? 0, $horas),
            'leidos'     => array_map(fn($h) => $leidos[$h]    ?? 0, $horas),
            'respondio' => array_map(fn($h) => $respondio[$h]  ?? 0, $horas),
        ];
    }

    public function render()
    {
        $q = CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id);

        match ($this->filtro) {
            'leyeron'      => $q->whereNotNull('leido_at'),
            'respondieron' => $q->whereNotNull('respondio_at'),
            'clicaron'     => $q->whereNotNull('boton_click_at'),
            'reaccionaron' => $q->whereNotNull('reaccion_at'),
            'convirtieron' => $q->whereNotNull('pedido_id'),
            'fallaron'     => $q->where('estado', CampanaDestinatario::ESTADO_FALLIDO),
            default        => null,
        };

        if ($this->busqueda) {
            $b = '%' . trim($this->busqueda) . '%';
            $q->where(fn($s) => $s->where('telefono', 'like', $b)->orWhere('nombre', 'like', $b));
        }

        $destinatarios = $q->orderByDesc('enviado_at')->paginate(25);

        return view('livewire.campanas.informe', [
            'destinatarios' => $destinatarios,
            'kpis'          => $this->kpis,
            'botones'       => $this->botones,
            'reacciones'    => $this->reacciones,
            'timeline'      => $this->timeline,
        ]);
    }
}
