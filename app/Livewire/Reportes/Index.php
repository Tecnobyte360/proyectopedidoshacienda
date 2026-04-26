<?php

namespace App\Livewire\Reportes;

use App\Models\DetallePedido;
use App\Models\Domiciliario;
use App\Models\EncuestaPedido;
use App\Models\Pedido;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $rango = 'semana';   // hoy | semana | mes | trimestre
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

        // Si el rango es "hoy", agrupamos por HORA para que se vea evolución
        if ($this->rango === 'hoy') {
            return $this->ventasPorHoraHoy($inicio, $fin);
        }

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

    /**
     * Para el rango "hoy": ventas agrupadas por hora (00-23).
     * Así la gráfica muestra evolución dentro del día en lugar de un solo punto.
     */
    private function ventasPorHoraHoy(Carbon $inicio, Carbon $fin): array
    {
        $datos = (clone $this->baseQuery())
            ->where('estado', '!=', 'cancelado')
            ->select(
                DB::raw("HOUR(fecha_pedido) as hora"),
                DB::raw('COALESCE(SUM(total), 0) as ventas'),
                DB::raw('COUNT(*) as pedidos')
            )
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->keyBy('hora');

        $resultado = [];
        for ($h = 0; $h < 24; $h++) {
            $resultado[] = [
                'dia'     => sprintf('%02d:00', $h),
                'ventas'  => (float) ($datos[$h]->ventas ?? 0),
                'pedidos' => (int) ($datos[$h]->pedidos ?? 0),
            ];
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

    private function baseEncuestas()
    {
        [$inicio, $fin] = $this->rangoFechas();

        return EncuestaPedido::query()
            ->whereBetween('created_at', [$inicio, $fin])
            ->when($this->sedeId, function ($q) {
                $q->whereHas('pedido', fn ($qq) => $qq->where('sede_id', $this->sedeId));
            });
    }

    #[Computed]
    public function satisfaccionKpis(): array
    {
        $base = $this->baseEncuestas();

        $enviadas    = (clone $base)->whereNotNull('enviada_at')->count();
        $vistas      = (clone $base)->whereNotNull('vista_at')->count();
        $completadas = (clone $base)->whereNotNull('completada_at')->count();

        $promProceso = (float) (clone $base)->whereNotNull('completada_at')->avg('calificacion_proceso');
        $promDom     = (float) (clone $base)->whereNotNull('completada_at')->avg('calificacion_domiciliario');

        $totalRecomendacion = (clone $base)->whereNotNull('completada_at')->whereNotNull('recomendaria')->count();
        $recomendarian = (clone $base)->whereNotNull('completada_at')->where('recomendaria', true)->count();
        $pctRecomienda = $totalRecomendacion > 0 ? round(($recomendarian / $totalRecomendacion) * 100, 1) : 0;

        $tasaRespuesta = $enviadas > 0 ? round(($completadas / $enviadas) * 100, 1) : 0;
        $tasaApertura  = $enviadas > 0 ? round(($vistas / $enviadas) * 100, 1) : 0;

        // NPS-lite a partir de calificación 1-5: promotores 5, detractores 1-3
        $promotores  = (clone $base)->whereNotNull('completada_at')->where('calificacion_proceso', 5)->count();
        $detractores = (clone $base)->whereNotNull('completada_at')->whereBetween('calificacion_proceso', [1, 3])->count();
        $nps = $completadas > 0 ? round((($promotores - $detractores) / $completadas) * 100) : 0;

        return [
            'enviadas'        => $enviadas,
            'vistas'          => $vistas,
            'completadas'     => $completadas,
            'tasa_respuesta'  => $tasaRespuesta,
            'tasa_apertura'   => $tasaApertura,
            'prom_proceso'    => round($promProceso, 2),
            'prom_domicilio'  => round($promDom, 2),
            'pct_recomienda'  => $pctRecomienda,
            'nps'             => $nps,
        ];
    }

    #[Computed]
    public function distribucionCalificaciones(): array
    {
        $base = $this->baseEncuestas()->whereNotNull('completada_at');

        $proceso = (clone $base)
            ->select('calificacion_proceso as estrellas', DB::raw('COUNT(*) as total'))
            ->whereNotNull('calificacion_proceso')
            ->groupBy('calificacion_proceso')
            ->pluck('total', 'estrellas')
            ->toArray();

        $dom = (clone $base)
            ->select('calificacion_domiciliario as estrellas', DB::raw('COUNT(*) as total'))
            ->whereNotNull('calificacion_domiciliario')
            ->groupBy('calificacion_domiciliario')
            ->pluck('total', 'estrellas')
            ->toArray();

        $resultado = [];
        for ($i = 5; $i >= 1; $i--) {
            $resultado[] = [
                'estrellas'    => $i,
                'proceso'      => (int) ($proceso[$i] ?? 0),
                'domiciliario' => (int) ($dom[$i] ?? 0),
            ];
        }
        return $resultado;
    }

    #[Computed]
    public function rankingDomiciliarios(): array
    {
        [$inicio, $fin] = $this->rangoFechas();

        return Domiciliario::query()
            ->select('domiciliarios.id', 'domiciliarios.nombre', 'domiciliarios.vehiculo')
            ->selectRaw('COUNT(encuestas_pedido.id) as encuestas')
            ->selectRaw('AVG(encuestas_pedido.calificacion_domiciliario) as promedio')
            ->leftJoin('encuestas_pedido', function ($j) use ($inicio, $fin) {
                $j->on('encuestas_pedido.domiciliario_id', '=', 'domiciliarios.id')
                  ->whereNotNull('encuestas_pedido.completada_at')
                  ->whereBetween('encuestas_pedido.created_at', [$inicio, $fin]);
            })
            ->groupBy('domiciliarios.id', 'domiciliarios.nombre', 'domiciliarios.vehiculo')
            ->havingRaw('COUNT(encuestas_pedido.id) > 0')
            ->orderByDesc('promedio')
            ->orderByDesc('encuestas')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'nombre'    => $d->nombre,
                'vehiculo'  => $d->vehiculo,
                'encuestas' => (int) $d->encuestas,
                'promedio'  => round((float) $d->promedio, 2),
            ])
            ->toArray();
    }

    #[Computed]
    public function comentariosRecientes(): array
    {
        return $this->baseEncuestas()
            ->whereNotNull('completada_at')
            ->where(function ($q) {
                $q->whereNotNull('comentario_proceso')
                  ->orWhereNotNull('comentario_domiciliario');
            })
            ->with(['pedido:id,cliente_nombre', 'domiciliario:id,nombre'])
            ->orderByDesc('completada_at')
            ->limit(6)
            ->get()
            ->map(fn ($e) => [
                'cliente'      => $e->pedido?->cliente_nombre ?? 'Cliente',
                'domiciliario' => $e->domiciliario?->nombre,
                'cal_proceso'  => $e->calificacion_proceso,
                'cal_dom'      => $e->calificacion_domiciliario,
                'com_proceso'  => $e->comentario_proceso,
                'com_dom'      => $e->comentario_domiciliario,
                'fecha'        => $e->completada_at?->diffForHumans(),
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
