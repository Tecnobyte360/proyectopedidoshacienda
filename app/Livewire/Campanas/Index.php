<?php

namespace App\Livewire\Campanas;

use App\Models\CampanaWhatsapp;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use App\Services\CampanaSenderService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public bool $modal = false;
    public ?int $editandoId = null;

    public string $nombre  = '';
    public string $mensaje = '';
    public string $audienciaTipo = 'todos';
    public ?int   $zonaId = null;
    public ?int   $sedeId = null;
    public int    $minPedidos = 1;
    public string $telefonosManual = '';

    public int    $intervaloMinSeg   = 8;
    public int    $intervaloMaxSeg   = 20;
    public int    $loteTamano        = 50;
    public int    $descansoLoteMin   = 30;
    public string $ventanaDesde      = '08:00';
    public string $ventanaHasta      = '20:00';

    public ?string $programadaPara = null;

    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:150',
            'mensaje'          => 'required|string|max:2000',
            'audienciaTipo'    => 'required|in:todos,zona,sede,con_pedidos,sin_pedidos,manual',
            'intervaloMinSeg'  => 'integer|min:1|max:300',
            'intervaloMaxSeg'  => 'integer|min:1|max:600',
            'loteTamano'       => 'integer|min:1|max:500',
            'descansoLoteMin'  => 'integer|min:0|max:1440',
            'ventanaDesde'     => 'string',
            'ventanaHasta'     => 'string',
        ];
    }

    public function render()
    {
        $campanas = CampanaWhatsapp::orderByDesc('id')->paginate(15);
        $zonas    = ZonaCobertura::where('activa', true)->orderBy('nombre')->get();
        $sedes    = Sede::orderBy('nombre')->get();

        return view('livewire.campanas.index', compact('campanas', 'zonas', 'sedes'))
            ->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'mensaje', 'zonaId', 'sedeId', 'telefonosManual', 'programadaPara']);
        $this->audienciaTipo   = 'todos';
        $this->minPedidos      = 1;
        $this->intervaloMinSeg = 8;
        $this->intervaloMaxSeg = 20;
        $this->loteTamano      = 50;
        $this->descansoLoteMin = 30;
        $this->ventanaDesde    = '08:00';
        $this->ventanaHasta    = '20:00';
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);
        $this->editandoId       = $c->id;
        $this->nombre           = $c->nombre;
        $this->mensaje          = $c->mensaje;
        $this->audienciaTipo    = $c->audiencia_tipo;
        $f = $c->audiencia_filtros ?? [];
        $this->zonaId           = $f['zona_id']      ?? null;
        $this->sedeId           = $f['sede_id']      ?? null;
        $this->minPedidos       = $f['min_pedidos']  ?? 1;
        $this->telefonosManual  = isset($f['telefonos']) ? implode("\n", $f['telefonos']) : '';
        $this->intervaloMinSeg  = $c->intervalo_min_seg;
        $this->intervaloMaxSeg  = $c->intervalo_max_seg;
        $this->loteTamano       = $c->lote_tamano;
        $this->descansoLoteMin  = $c->descanso_lote_min;
        $this->ventanaDesde     = substr($c->ventana_desde ?: '08:00:00', 0, 5);
        $this->ventanaHasta     = substr($c->ventana_hasta ?: '20:00:00', 0, 5);
        $this->programadaPara   = $c->programada_para?->format('Y-m-d\TH:i');
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $filtros = [];
        if ($this->audienciaTipo === 'zona' && $this->zonaId)         $filtros['zona_id'] = $this->zonaId;
        if ($this->audienciaTipo === 'sede' && $this->sedeId)         $filtros['sede_id'] = $this->sedeId;
        if ($this->audienciaTipo === 'con_pedidos')                   $filtros['min_pedidos'] = $this->minPedidos;
        if ($this->audienciaTipo === 'manual')                        $filtros['telefonos'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->telefonosManual))));

        $data = [
            'nombre'             => $this->nombre,
            'mensaje'            => $this->mensaje,
            'audiencia_tipo'     => $this->audienciaTipo,
            'audiencia_filtros'  => $filtros,
            'intervalo_min_seg'  => $this->intervaloMinSeg,
            'intervalo_max_seg'  => max($this->intervaloMaxSeg, $this->intervaloMinSeg),
            'lote_tamano'        => $this->loteTamano,
            'descanso_lote_min'  => $this->descansoLoteMin,
            'ventana_desde'      => $this->ventanaDesde . ':00',
            'ventana_hasta'      => $this->ventanaHasta . ':00',
            'programada_para'    => $this->programadaPara ?: null,
            'estado'             => $this->programadaPara ? CampanaWhatsapp::ESTADO_PROGRAMADA : CampanaWhatsapp::ESTADO_BORRADOR,
            'creado_por'         => auth()->id(),
        ];

        if ($this->editandoId) {
            CampanaWhatsapp::findOrFail($this->editandoId)->update($data);
        } else {
            CampanaWhatsapp::create($data);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Campaña guardada']);
    }

    public function generarAudiencia(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);
        $count = app(CampanaSenderService::class)->generarAudiencia($c);
        $this->dispatch('notify', ['type' => 'success', 'message' => "✓ {$count} destinatarios generados."]);
    }

    public function iniciar(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);

        if ($c->total_destinatarios === 0) {
            app(CampanaSenderService::class)->generarAudiencia($c);
            $c->refresh();
        }

        if ($c->total_destinatarios === 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sin destinatarios. Revisa la audiencia.']);
            return;
        }

        $c->update([
            'estado'      => CampanaWhatsapp::ESTADO_CORRIENDO,
            'iniciada_at' => $c->iniciada_at ?: now(),
        ]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '▶️ Campaña iniciada. Procesará en lotes según el cron.']);
    }

    public function pausar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_PAUSADA]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '⏸ Pausada']);
    }

    public function reanudar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CORRIENDO]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '▶️ Reanudada']);
    }

    public function cancelar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CANCELADA]);
        $this->dispatch('notify', ['type' => 'warning', 'message' => '✕ Cancelada']);
    }

    public function eliminar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Eliminada']);
    }
}
