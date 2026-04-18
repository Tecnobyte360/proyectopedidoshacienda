<?php

namespace App\Livewire\Felicitaciones;

use App\Models\FelicitacionCumpleanos;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filtroEstado = 'todas';  // todas | enviado | fallido | dry_run
    public string $filtroOrigen = 'todos';  // todos | scheduled | manual | force
    public string $busqueda     = '';
    public ?int   $anio         = null;

    public ?int $detalleId = null;

    protected $queryString = ['filtroEstado', 'filtroOrigen', 'busqueda', 'anio'];

    public function mount(): void
    {
        $this->anio = (int) now()->format('Y');
    }

    public function updating($name): void
    {
        if (in_array($name, ['filtroEstado', 'filtroOrigen', 'busqueda', 'anio'], true)) {
            $this->resetPage();
        }
    }

    public function abrirDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function reintentar(int $id): void
    {
        $reg = FelicitacionCumpleanos::find($id);
        if (!$reg) return;

        if ($reg->estado !== FelicitacionCumpleanos::ESTADO_FALLIDO) {
            $this->dispatch('notify', [
                'type'    => 'info',
                'message' => 'Solo se pueden reintentar felicitaciones fallidas.',
            ]);
            return;
        }

        try {
            $wa = app(\App\Services\WhatsappSenderService::class);
            $ok = $wa->enviarTexto($reg->telefono, $reg->mensaje);

            if ($ok) {
                $reg->update([
                    'estado'        => FelicitacionCumpleanos::ESTADO_ENVIADO,
                    'error_detalle' => null,
                    'enviado_at'    => now(),
                ]);
                if ($reg->cliente_id) {
                    \App\Models\Cliente::where('id', $reg->cliente_id)
                        ->update(['ultima_felicitacion_anio' => $reg->anio]);
                }
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => '✅ Reintento exitoso. Mensaje enviado.',
                ]);
            } else {
                $reg->update([
                    'error_detalle' => 'Reintento falló. Verifica token de WhatsApp.',
                    'enviado_at'    => now(),
                ]);
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => '❌ El reintento también falló.',
                ]);
            }
        } catch (\Throwable $e) {
            $reg->update(['error_detalle' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $query = FelicitacionCumpleanos::query();

        if ($this->anio) {
            $query->where('anio', $this->anio);
        }

        if ($this->filtroEstado !== 'todas') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroOrigen !== 'todos') {
            $query->where('origen', $this->filtroOrigen);
        }

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('cliente_nombre', 'like', "%{$b}%")
                  ->orWhere('telefono', 'like', "%{$b}%");
            });
        }

        $felicitaciones = $query
            ->orderByDesc('enviado_at')
            ->paginate(20);

        $anioBase = $this->anio ?: (int) now()->format('Y');
        $totales = [
            'total'    => FelicitacionCumpleanos::where('anio', $anioBase)->count(),
            'enviados' => FelicitacionCumpleanos::where('anio', $anioBase)->where('estado', 'enviado')->count(),
            'fallidos' => FelicitacionCumpleanos::where('anio', $anioBase)->where('estado', 'fallido')->count(),
            'dry_run'  => FelicitacionCumpleanos::where('anio', $anioBase)->where('estado', 'dry_run')->count(),
        ];

        $aniosDisponibles = FelicitacionCumpleanos::query()
            ->select('anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio')
            ->toArray();

        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int) now()->format('Y')];
        }

        $detalle = $this->detalleId ? FelicitacionCumpleanos::find($this->detalleId) : null;

        return view('livewire.felicitaciones.index', compact(
            'felicitaciones',
            'totales',
            'aniosDisponibles',
            'detalle'
        ))->layout('layouts.app');
    }
}
