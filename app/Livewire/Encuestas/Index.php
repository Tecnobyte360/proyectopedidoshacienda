<?php

namespace App\Livewire\Encuestas;

use App\Models\EncuestaPedido;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filtro = 'todas';   // todas | completadas | pendientes | bajas
    public string $busqueda = '';
    public ?int $domiciliarioId = null;

    protected $paginationTheme = 'tailwind';

    public function updating($name): void { $this->resetPage(); }

    public function getEstadisticasProperty(): array
    {
        $base = EncuestaPedido::query();

        $total       = (clone $base)->count();
        $completadas = (clone $base)->whereNotNull('completada_at')->count();
        $promProc    = (clone $base)->whereNotNull('calificacion_proceso')->avg('calificacion_proceso');
        $promDom     = (clone $base)->whereNotNull('calificacion_domiciliario')->avg('calificacion_domiciliario');
        $recom       = (clone $base)->where('recomendaria', true)->count();
        $noRecom     = (clone $base)->where('recomendaria', false)->count();

        return [
            'total'       => $total,
            'completadas' => $completadas,
            'pendientes'  => $total - $completadas,
            'tasa'        => $total > 0 ? round($completadas / $total * 100, 1) : 0,
            'prom_proc'   => round((float) $promProc, 1),
            'prom_dom'    => round((float) $promDom, 1),
            'recom_si'    => $recom,
            'recom_no'    => $noRecom,
        ];
    }

    public function render()
    {
        $q = EncuestaPedido::with(['pedido:id,cliente_nombre,total', 'domiciliario:id,nombre'])
            ->when($this->filtro === 'completadas', fn ($qq) => $qq->whereNotNull('completada_at'))
            ->when($this->filtro === 'pendientes',  fn ($qq) => $qq->whereNull('completada_at'))
            ->when($this->filtro === 'bajas',       fn ($qq) => $qq->where('calificacion_proceso', '<=', 3))
            ->when($this->domiciliarioId, fn ($qq) => $qq->where('domiciliario_id', $this->domiciliarioId))
            ->when($this->busqueda, function ($qq) {
                $qq->whereHas('pedido', fn ($p) => $p->where('cliente_nombre', 'like', "%{$this->busqueda}%"));
            })
            ->orderByDesc('id');

        return view('livewire.encuestas.index', [
            'encuestas'      => $q->paginate(20),
            'estadisticas'   => $this->estadisticas,
            'domiciliarios'  => \App\Models\Domiciliario::orderBy('nombre')->get(['id','nombre']),
        ])->layout('layouts.app');
    }
}
