<?php

namespace App\Livewire\Pagos;

use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $rango     = 'mes';   // hoy | semana | mes | trimestre | todo
    public string $estado    = '';      // '' | aprobado | pendiente | rechazado | fallido | reembolsado
    public string $busqueda  = '';      // referencia, telefono, cliente, transaction_id

    public function actualizar(): void
    {
        // dispara render
    }

    /**
     * Genera un link de pago nuevo (rotando la reference) para un pedido.
     * Útil cuando un intento previo fue abandonado o rechazado y Wompi
     * no permite reusar la misma reference.
     */
    /**
     * Consulta el estado real en la API de Wompi y actualiza el pedido.
     * Útil cuando un webhook se perdió o llegó tarde.
     */
    public function sincronizarConWompi(int $pedidoId): void
    {
        $pedido = Pedido::find($pedidoId);
        if (!$pedido) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Pedido no encontrado.']);
            return;
        }

        $r = app(\App\Services\WompiService::class)->sincronizarPedido($pedido);

        if (!$r['ok']) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => $r['mensaje']]);
            return;
        }

        $iconos = [
            'aprobado' => '✅',
            'rechazado' => '❌',
            'fallido'  => '⚠️',
            'pendiente' => '⏳',
            'reembolsado' => '↩️',
        ];
        $icono = $iconos[$r['estado']] ?? 'ℹ️';

        $this->dispatch('notify', [
            'type'    => $r['estado'] === 'aprobado' ? 'success' : 'info',
            'message' => "{$icono} {$r['mensaje']}",
        ]);
    }

    public function regenerarLink(int $pedidoId): void
    {
        $pedido = Pedido::find($pedidoId);
        if (!$pedido) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Pedido no encontrado.']);
            return;
        }

        $url = app(\App\Services\WompiService::class)->urlPago($pedido, forzarRotacion: true);

        if (!$url) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Wompi no está configurado para este tenant.']);
            return;
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✅ Link de pago regenerado. Nueva referencia: ' . $pedido->fresh()->wompi_reference,
        ]);

        // Forzar refresh
        $this->dispatch('$refresh');
    }

    protected function queryString(): array
    {
        return [
            'rango'   => ['except' => 'mes'],
            'estado'  => ['except' => ''],
            'busqueda' => ['except' => ''],
        ];
    }

    public function updatingBusqueda(): void { $this->resetPage(); }
    public function updatingEstado(): void   { $this->resetPage(); }
    public function updatingRango(): void    { $this->resetPage(); }

    private function rangoFechas(): ?array
    {
        if ($this->rango === 'todo') return null;

        $fin = Carbon::now()->endOfDay();
        $inicio = match ($this->rango) {
            'hoy'       => Carbon::now()->startOfDay(),
            'semana'    => Carbon::now()->subDays(6)->startOfDay(),
            'trimestre' => Carbon::now()->subDays(89)->startOfDay(),
            default     => Carbon::now()->subDays(29)->startOfDay(), // 'mes'
        };
        return [$inicio, $fin];
    }

    private function baseQuery()
    {
        $q = Pedido::query()->whereNotNull('wompi_reference');

        $r = $this->rangoFechas();
        if ($r) {
            [$inicio, $fin] = $r;
            $q->whereBetween('fecha_pedido', [$inicio, $fin]);
        }

        if ($this->estado !== '') {
            $q->where('estado_pago', $this->estado);
        }

        if (trim($this->busqueda) !== '') {
            $b = '%' . trim($this->busqueda) . '%';
            $q->where(function ($qq) use ($b) {
                $qq->where('wompi_reference', 'like', $b)
                   ->orWhere('wompi_transaction_id', 'like', $b)
                   ->orWhere('cliente_nombre', 'like', $b)
                   ->orWhere('telefono_whatsapp', 'like', $b)
                   ->orWhere('id', is_numeric(trim($this->busqueda)) ? (int) trim($this->busqueda) : 0);
            });
        }

        return $q;
    }

    #[Computed]
    public function kpis(): array
    {
        $base = $this->baseQuery();

        $totalCobrado = (float) (clone $base)->where('estado_pago', 'aprobado')->sum('total');
        $countAprobado = (clone $base)->where('estado_pago', 'aprobado')->count();
        $countPendiente = (clone $base)->where('estado_pago', 'pendiente')->count();
        $totalPendiente = (float) (clone $base)->where('estado_pago', 'pendiente')->sum('total');
        $countRechazado = (clone $base)->whereIn('estado_pago', ['rechazado', 'fallido'])->count();

        $total = (clone $base)->count();
        $tasaConversion = $total > 0 ? round(($countAprobado / $total) * 100, 1) : 0;

        return [
            'total_cobrado'   => $totalCobrado,
            'count_aprobado' => $countAprobado,
            'count_pendiente' => $countPendiente,
            'total_pendiente' => $totalPendiente,
            'count_rechazado' => $countRechazado,
            'total'           => $total,
            'tasa_conversion' => $tasaConversion,
        ];
    }

    #[Computed]
    public function metodosPago(): array
    {
        $base = $this->baseQuery()->where('estado_pago', 'aprobado');

        return (clone $base)
            ->select('pago_metodo', DB::raw('COUNT(*) as total'), DB::raw('SUM(total) as monto'))
            ->whereNotNull('pago_metodo')
            ->groupBy('pago_metodo')
            ->orderByDesc('monto')
            ->get()
            ->map(fn ($r) => [
                'metodo' => $r->pago_metodo,
                'total'  => (int) $r->total,
                'monto'  => (float) $r->monto,
            ])
            ->toArray();
    }

    public function render()
    {
        $pagos = $this->baseQuery()
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.pagos.index', compact('pagos'))
            ->layout('layouts.app');
    }
}
