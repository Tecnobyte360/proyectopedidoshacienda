<?php

namespace App\Livewire\Reportes;

use App\Models\DetallePedido;
use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $rango = 'hoy';   // hoy | semana | mes | trimestre
    public ?int   $sedeId = null;

    public function actualizar(): void
    {
        // sólo dispara render
    }

    private function rangoFechas(): array
    {
        $fin = Carbon::now()->endOfDay();

        $inicio = match ($this->rango) {
            'hoy'       => Carbon::now()->startOfDay(),
            'semana'    => Carbon::now()->subDays(6)->startOfDay(),
            'mes'       => Carbon::now()->subDays(29)->startOfDay(),
            'trimestre' => Carbon::now()->subDays(89)->startOfDay(),
            default     => Carbon::now()->startOfDay(),
        };

        return [$inicio, $fin];
    }

    private function baseQuery()
    {
        [$inicio, $fin] = $this->rangoFechas();

        return Pedido::query()
            ->whereBetween('fecha_pedido', [$inicio, $fin])
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId));
    }

    #[Computed]
    public function kpis(): array
    {
        $base = $this->baseQuery();

        $total      = (clone $base)->count();
        $entregados = (clone $base)->where('estado', 'entregado')->count();
        $cancelados = (clone $base)->where('estado', 'cancelado')->count();
        $ingresos   = (float) (clone $base)->where('estado', '!=', 'cancelado')->sum('total');
        $ticket     = $entregados > 0
            ? (float) (clone $base)->where('estado', 'entregado')->sum('total') / $entregados
            : 0;

        $tasaEntrega = $total > 0 ? round(($entregados / $total) * 100, 1) : 0;

        return [
            'total'        => $total,
            'entregados'   => $entregados,
            'cancelados'   => $cancelados,
            'ingresos'     => $ingresos,
            'ticket'       => $ticket,
            'tasa_entrega' => $tasaEntrega,
        ];
    }

    #[Computed]
    public function porEstado(): array
    {
        $datos = (clone $this->baseQuery())
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $estados = [
            'nuevo'                 => ['Nuevo', '#3b82f6'],
            'en_preparacion'        => ['En preparación', '#f59e0b'],
            'repartidor_en_camino'  => ['En camino', '#8b5cf6'],
            'entregado'             => ['Entregado', '#10b981'],
            'cancelado'             => ['Cancelado', '#ef4444'],
        ];

        $resultado = [];
        foreach ($estados as $key => [$label, $color]) {
            $resultado[] = [
                'estado' => $label,
                'total'  => $datos[$key] ?? 0,
                'color'  => $color,
            ];
        }

        return $resultado;
    }

    #[Computed]
    public function ventasPorDia(): array
    {
        [$inicio, $fin] = $this->rangoFechas();

        $datos = (clone $this->baseQuery())
            ->where('estado', '!=', 'cancelado')
            ->select(
                DB::raw("DATE(fecha_pedido) as dia"),
                DB::raw('COALESCE(SUM(total), 0) as ventas'),
                DB::raw('COUNT(*) as pedidos')
            )
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $resultado = [];
        $cursor = $inicio->copy();

        while ($cursor->lte($fin)) {
            $key = $cursor->format('Y-m-d');
            $resultado[] = [
                'dia'      => $cursor->format('d M'),
                'ventas'   => (float) ($datos[$key]->ventas ?? 0),
                'pedidos'  => (int) ($datos[$key]->pedidos ?? 0),
            ];
            $cursor->addDay();
        }

        return $resultado;
    }

    #[Computed]
    public function topProductos(): array
    {
        [$inicio, $fin] = $this->rangoFechas();

        return DetallePedido::query()
            ->select('producto', DB::raw('SUM(cantidad) as cantidad'), DB::raw('SUM(subtotal) as total'))
            ->whereHas('pedido', function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha_pedido', [$inicio, $fin])
                  ->where('estado', '!=', 'cancelado')
                  ->when($this->sedeId, fn ($qq) => $qq->where('sede_id', $this->sedeId));
            })
            ->groupBy('producto')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'producto' => $r->producto,
                'cantidad' => (float) $r->cantidad,
                'total'    => (float) $r->total,
            ])
            ->toArray();
    }

    #[Computed]
    public function topDomiciliarios(): array
    {
        [$inicio, $fin] = $this->rangoFechas();

        return Domiciliario::query()
            ->withCount(['pedidos as entregas' => function ($q) use ($inicio, $fin) {
                $q->where('estado', 'entregado')
                  ->whereBetween('fecha_pedido', [$inicio, $fin])
                  ->when($this->sedeId, fn ($qq) => $qq->where('sede_id', $this->sedeId));
            }])
            ->orderByDesc('entregas')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id'       => $d->id,
                'nombre'   => $d->nombre,
                'vehiculo' => $d->vehiculo,
                'estado'   => $d->estado,
                'entregas' => (int) $d->entregas,
            ])
            ->toArray();
    }

    #[Computed]
    public function ventasPorSede(): array
    {
        [$inicio, $fin] = $this->rangoFechas();

        return Sede::query()
            ->select('sedes.id', 'sedes.nombre')
            ->selectRaw('COUNT(pedidos.id) as pedidos')
            ->selectRaw('COALESCE(SUM(CASE WHEN pedidos.estado != \'cancelado\' THEN pedidos.total ELSE 0 END), 0) as ventas')
            ->leftJoin('pedidos', function ($join) use ($inicio, $fin) {
                $join->on('pedidos.sede_id', '=', 'sedes.id')
                     ->whereBetween('pedidos.fecha_pedido', [$inicio, $fin]);
            })
            ->groupBy('sedes.id', 'sedes.nombre')
            ->orderByDesc('ventas')
            ->get()
            ->map(fn ($r) => [
                'sede'    => $r->nombre,
                'pedidos' => (int) $r->pedidos,
                'ventas'  => (float) $r->ventas,
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.reportes.index', [
            'sedes' => Sede::orderBy('nombre')->get(),
        ])->layout('layouts.app');
    }
}
