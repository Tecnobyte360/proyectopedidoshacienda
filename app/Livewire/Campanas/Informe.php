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

    /**
     * 🤖 Clasifica con IA la respuesta de cada cliente que contestó la campaña:
     * Interesado / No interesado / Duda. Procesa por tandas.
     */
    public function analizarInteresados(): void
    {
        $tenantId = $this->campana->tenant_id;
        app(\App\Services\TenantManager::class)->set(\App\Models\Tenant::withoutGlobalScopes()->find($tenantId));

        $pendientes = CampanaDestinatario::withoutGlobalScopes()
            ->where('campana_id', $this->campana->id)
            ->whereNotNull('respondio_at')
            ->whereNull('interes')
            ->limit(40) // por tanda, para no demorar
            ->get();

        if ($pendientes->isEmpty()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No hay respuestas nuevas por analizar.']);
            return;
        }

        $ai = app(\App\Services\Ai\AiClientService::class);
        $contextoCampana = trim((string) ($this->campana->mensaje ?: $this->campana->plantilla_meta_nombre));
        $analizados = 0;

        foreach ($pendientes as $d) {
            $telNorm = preg_replace('/\D+/', '', (string) $d->telefono);
            $conv = \App\Models\ConversacionWhatsapp::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('telefono_normalizado', $telNorm)
                ->orderByDesc('id')->first();

            $reply = null;
            if ($conv) {
                $reply = $conv->mensajes()
                    ->where('rol', \App\Models\MensajeWhatsapp::ROL_USER)
                    ->when($d->enviado_at, fn ($q) => $q->where('created_at', '>=', $d->enviado_at))
                    ->orderByDesc('id')->value('contenido');
            }
            if (!$reply) continue;

            try {
                $resp = $ai->chat([
                    ['role' => 'system', 'content' =>
                        "Eres un clasificador de intención comercial. Te paso el mensaje de una campaña y la respuesta de un cliente. "
                        . "Clasifica el INTERÉS del cliente en comprar/cotizar. Responde SOLO un JSON válido: "
                        . '{"interes":"interesado|no_interesado|duda","motivo":"máx 8 palabras"}. '
                        . "'interesado' = muestra intención de comprar, cotizar o saber precio. "
                        . "'no_interesado' = rechaza o dice que no le sirve. "
                        . "'duda' = pregunta algo o es ambiguo."],
                    ['role' => 'user', 'content' => "Campaña: {$contextoCampana}\nRespuesta del cliente: {$reply}"],
                ], 'none', null, ['temperature' => 0, 'max_tokens' => 80]);

                $txt  = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
                $txt  = trim(preg_replace('/```json|```/', '', $txt));
                $json = json_decode($txt, true);
                $interes = $json['interes'] ?? null;
                if (!in_array($interes, ['interesado', 'no_interesado', 'duda'], true)) $interes = 'duda';

                $d->update([
                    'interes'         => $interes,
                    'interes_motivo'  => mb_substr((string) ($json['motivo'] ?? ''), 0, 255),
                    'respuesta_texto' => mb_substr((string) $reply, 0, 1000),
                ]);
                $analizados++;
            } catch (\Throwable $e) {
                \Log::warning('Analizar interés campaña falló: ' . $e->getMessage());
            }
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => "✅ Analizados {$analizados} clientes con IA."]);
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
        $interesados   = (clone $q)->where('interes', 'interesado')->count();
        $noInteresados = (clone $q)->where('interes', 'no_interesado')->count();
        $dudas         = (clone $q)->where('interes', 'duda')->count();
        $sinAnalizar   = (clone $q)->whereNotNull('respondio_at')->whereNull('interes')->count();
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
            'interesados'   => $interesados,
            'no_interesados'=> $noInteresados,
            'dudas'         => $dudas,
            'sin_analizar'  => $sinAnalizar,
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

    /**
     * Botones más clicados — agrega TODOS los clicks (no solo el primero por persona).
     * Lee del historial JSON botones_clicks; cae a boton_click si no hay historial.
     */
    public function getBotonesProperty(): array
    {
        $destinatarios = CampanaDestinatario::query()
            ->withoutGlobalScopes()
            ->where('campana_id', $this->campana->id)
            ->where(fn($q) => $q->whereNotNull('boton_click')->orWhereNotNull('botones_clicks'))
            ->get(['boton_click', 'botones_clicks']);

        $conteo = [];
        foreach ($destinatarios as $d) {
            // Preferir historial JSON; si no hay, usar boton_click clásico
            $clicks = is_array($d->botones_clicks) && !empty($d->botones_clicks)
                ? array_column($d->botones_clicks, 'boton')
                : ($d->boton_click ? [$d->boton_click] : []);

            foreach ($clicks as $btn) {
                $conteo[$btn] = ($conteo[$btn] ?? 0) + 1;
            }
        }

        arsort($conteo);
        return collect($conteo)->map(fn($n, $btn) => ['boton_click' => $btn, 'n' => $n])->values()->toArray();
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
            'interesados'  => $q->where('interes', 'interesado'),
            'no_interesados' => $q->where('interes', 'no_interesado'),
            'dudas'        => $q->where('interes', 'duda'),
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
