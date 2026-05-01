<?php

namespace App\Livewire\Monitoreo;

use App\Models\AgenteToolInvocacion;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Agente extends Component
{
    use WithPagination;

    public string $rango       = 'hoy';   // hoy | 7d | 30d
    public string $filtroTool  = 'todas';
    public string $busqueda    = '';      // busca en args/telefono
    public bool   $autoRefresh = true;
    public ?int   $verDetalleId = null;

    protected $listeners = ['refresh' => '$refresh'];

    public function updating($name, $value): void
    {
        if (in_array($name, ['rango', 'filtroTool', 'busqueda'], true)) {
            $this->resetPage();
        }
    }

    public function verDetalle(int $id): void
    {
        $this->verDetalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->verDetalleId = null;
    }

    private function rangoFechas(): array
    {
        return match ($this->rango) {
            '7d'    => [now()->subDays(7), now()],
            '30d'   => [now()->subDays(30), now()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    public function render()
    {
        [$desde, $hasta] = $this->rangoFechas();

        $base = AgenteToolInvocacion::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($this->filtroTool !== 'todas', fn ($q) => $q->where('tool_name', $this->filtroTool))
            ->when($this->busqueda !== '', function ($q) {
                $like = '%' . $this->busqueda . '%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('telefono_cliente', 'like', $like)
                       ->orWhere('args', 'like', $like)
                       ->orWhere('tool_name', 'like', $like);
                });
            });

        // KPIs
        $totalInvocaciones = (clone $base)->count();
        $tasaExito = $totalInvocaciones > 0
            ? round((clone $base)->where('exitoso', true)->count() / $totalInvocaciones * 100, 1)
            : 0;
        $latenciaProm = (int) (clone $base)->avg('latencia_ms');
        $sinResultados = (clone $base)->where('count_resultados', 0)->where('exitoso', true)->count();

        // Distribución por tool
        $porTool = AgenteToolInvocacion::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->select('tool_name', DB::raw('COUNT(*) as total'), DB::raw('AVG(latencia_ms) as latencia'))
            ->groupBy('tool_name')
            ->orderByDesc('total')
            ->get();

        $maxPorTool = $porTool->max('total') ?: 1;

        // Top queries en buscar_productos
        $topQueries = AgenteToolInvocacion::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->where('tool_name', 'buscar_productos')
            ->get()
            ->map(fn ($i) => trim((string) ($i->args['query'] ?? '')))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(8);

        // Lista paginada (ultimas)
        $invocaciones = (clone $base)
            ->orderByDesc('id')
            ->paginate(20);

        // Tools disponibles para filtrar
        $toolsDisponibles = ['buscar_productos', 'listar_categorias', 'productos_de_categoria', 'info_producto', 'productos_destacados'];

        $detalle = $this->verDetalleId
            ? AgenteToolInvocacion::with('conversacion')->find($this->verDetalleId)
            : null;

        return view('livewire.monitoreo.agente', compact(
            'invocaciones',
            'totalInvocaciones',
            'tasaExito',
            'latenciaProm',
            'sinResultados',
            'porTool',
            'maxPorTool',
            'topQueries',
            'toolsDisponibles',
            'detalle',
        ));
    }
}
