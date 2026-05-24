<?php

namespace App\Livewire\Monitoreo;

use App\Models\WatchdogRescate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Watchdog extends Component
{
    public int  $horas = 24;
    public bool $mostrarResueltos = false;

    public function mount(): void {}

    #[Computed]
    public function rescates()
    {
        return WatchdogRescate::query()
            ->where('created_at', '>=', now()->subHours($this->horas))
            ->when(!$this->mostrarResueltos, fn ($q) => $q->whereNull('resuelto_at'))
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
        $fallidosActivos = (clone $base)->where('exitoso', false)->whereNull('resuelto_at')->count();
        $promedioSegs = (int) round((clone $base)->avg('segundos_estancada') ?: 0);
        $ultimo = (clone $base)->orderByDesc('id')->first();
        $clientesUnicos = (clone $base)->distinct('telefono')->count('telefono');

        return [
            'total'           => $total,
            'exitosos'        => $exitosos,
            'fallidos'        => $fallidosActivos,
            'tasa_exito'      => $total > 0 ? round(($exitosos / $total) * 100, 1) : 0.0,
            'promedio_segs'   => $promedioSegs,
            'ultimo_at'       => $ultimo?->created_at,
            'clientes_unicos' => $clientesUnicos,
        ];
    }

    public function marcarResuelto(int $id): void
    {
        WatchdogRescate::where('id', $id)->update([
            'resuelto_at'           => now(),
            'resuelto_por_user_id'  => auth()->id(),
        ]);
        unset($this->rescates, $this->kpis); // invalida computed
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Marcado como resuelto']);
    }

    public function marcarTodosResueltos(): void
    {
        $n = WatchdogRescate::query()
            ->where('created_at', '>=', now()->subHours($this->horas))
            ->whereNull('resuelto_at')
            ->where('exitoso', false)
            ->update([
                'resuelto_at'          => now(),
                'resuelto_por_user_id' => auth()->id(),
            ]);
        unset($this->rescates, $this->kpis);
        $this->dispatch('notify', ['type' => 'success', 'message' => "{$n} marcados como resueltos"]);
    }

    public function render()
    {
        return view('livewire.monitoreo.watchdog')->layout('layouts.app');
    }
}
