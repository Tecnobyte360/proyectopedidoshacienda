<?php

namespace App\Livewire\Monitoreo;

use App\Models\WatchdogRescate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Watchdog extends Component
{
    public int $horas = 24;

    /**
     * Livewire 3: re-render automático cada 5 segundos para que sea "en vivo".
     */
    public function mount(): void {}

    #[Computed]
    public function rescates()
    {
        return WatchdogRescate::query()
            ->where('created_at', '>=', now()->subHours($this->horas))
            ->with(['conversacion:id,cliente_id,telefono_normalizado', 'conversacion.cliente:id,nombre'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function kpis(): array
    {
        $base = WatchdogRescate::query()->where('created_at', '>=', now()->subHours($this->horas));
        $total = (clone $base)->count();
        $exitosos = (clone $base)->where('exitoso', true)->count();
        $fallidos = $total - $exitosos;
        $promedioSegs = (int) round((clone $base)->avg('segundos_estancada') ?: 0);
        $ultimo = (clone $base)->orderByDesc('id')->first();
        $clientesUnicos = (clone $base)->distinct('telefono')->count('telefono');

        return [
            'total'           => $total,
            'exitosos'        => $exitosos,
            'fallidos'        => $fallidos,
            'tasa_exito'      => $total > 0 ? round(($exitosos / $total) * 100, 1) : 0.0,
            'promedio_segs'   => $promedioSegs,
            'ultimo_at'       => $ultimo?->created_at,
            'clientes_unicos' => $clientesUnicos,
        ];
    }

    public function render()
    {
        return view('livewire.monitoreo.watchdog')->layout('layouts.app');
    }
}
