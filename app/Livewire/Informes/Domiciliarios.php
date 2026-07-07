<?php

namespace App\Livewire\Informes;

use App\Models\Domiciliario;
use App\Models\Pedido;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * 📊 Informe: pedidos del día en una tabla, filtrable por domiciliario.
 */
class Domiciliarios extends Component
{
    public string $desde = '';
    public string $hasta = '';
    public string $filtroEstado = 'todos';      // todos | entregados
    public string $filtroDomiciliario = '';     // '' = todos | 'sin' = sin asignar | id

    public function mount(): void
    {
        $hoy = now('America/Bogota')->toDateString();
        $this->desde = $hoy;
        $this->hasta = $hoy;
    }

    public function render()
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();

        // Rango de fechas (inclusivo). Si están al revés, se corrigen solos.
        $desde = Carbon::parse($this->desde ?: now('America/Bogota'), 'America/Bogota')->startOfDay();
        $hasta = Carbon::parse($this->hasta ?: $this->desde, 'America/Bogota')->endOfDay();
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        $base = Pedido::query()
            ->when($tenantId, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$desde, $hasta]);

        // Lista de domiciliarios para el filtro (los que tienen pedidos ese día)
        $idsDomi = (clone $base)->whereNotNull('domiciliario_id')->distinct()->pluck('domiciliario_id');
        $domiciliarios = Domiciliario::whereIn('id', $idsDomi)->orderBy('nombre')->get(['id', 'nombre']);

        // Aplicar filtros
        $q = (clone $base)->with(['domiciliario:id,nombre']);

        if ($this->filtroEstado === 'entregados') {
            $q->where('estado', Pedido::ESTADO_ENTREGADO);
        }
        if ($this->filtroDomiciliario === 'sin') {
            $q->whereNull('domiciliario_id');
        } elseif ($this->filtroDomiciliario !== '') {
            $q->where('domiciliario_id', (int) $this->filtroDomiciliario);
        }

        $pedidos = $q->orderBy('domiciliario_id')->orderBy('id')->get();

        $kpis = [
            'total_pedidos' => $pedidos->count(),
            'total_valor'   => (float) $pedidos->sum('total'),
            'entregados'    => $pedidos->where('estado', Pedido::ESTADO_ENTREGADO)->count(),
        ];

        return view('livewire.informes.domiciliarios', compact('pedidos', 'domiciliarios', 'kpis'))
            ->layout('layouts.app');
    }
}
