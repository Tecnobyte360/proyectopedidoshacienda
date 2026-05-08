<?php

namespace App\Livewire\Integraciones;

use App\Models\Integracion;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ClientesErp extends Component
{
    use WithPagination;

    public string $filtroAccion = 'todos'; // todos / buscar / crear
    public string $filtroEstado = 'todos'; // todos / encontrado / no_encontrado / creado / error
    public ?int   $filtroIntegracion = null;
    public ?int   $verLogId = null;
    public string $busquedaCedula = '';

    protected $paginationTheme = 'tailwind';

    public function render()
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();

        $q = DB::table('cliente_erp_lookups as l')
            ->leftJoin('integraciones as i', 'l.integracion_id', '=', 'i.id')
            ->select(
                'l.*',
                'i.nombre as integracion_nombre',
                'i.tipo as integracion_tipo'
            )
            ->where('l.tenant_id', $tenantId)
            ->orderByDesc('l.id');

        if ($this->filtroAccion !== 'todos') {
            $q->where('l.accion', $this->filtroAccion);
        }

        if ($this->filtroEstado !== 'todos') {
            switch ($this->filtroEstado) {
                case 'encontrado':
                    $q->where('l.accion', 'buscar')->where('l.encontrado', true);
                    break;
                case 'no_encontrado':
                    $q->where('l.accion', 'buscar')->where('l.encontrado', false)->where('l.exitoso', true);
                    break;
                case 'creado':
                    $q->where('l.accion', 'crear')->where('l.exitoso', true);
                    break;
                case 'error':
                    $q->where('l.exitoso', false);
                    break;
            }
        }

        if ($this->filtroIntegracion) {
            $q->where('l.integracion_id', $this->filtroIntegracion);
        }

        if (trim($this->busquedaCedula) !== '') {
            $q->where('l.cedula', 'like', '%' . trim($this->busquedaCedula) . '%');
        }

        $logs = $q->paginate(20);

        $integraciones = Integracion::where('tenant_id', $tenantId)
            ->where('exporta_pedidos', true)
            ->get(['id', 'nombre']);

        // Stats
        $base = DB::table('cliente_erp_lookups')->where('tenant_id', $tenantId);
        $stats = [
            'total'         => (clone $base)->count(),
            'encontrados'   => (clone $base)->where('accion', 'buscar')->where('encontrado', true)->count(),
            'no_encontrados'=> (clone $base)->where('accion', 'buscar')->where('encontrado', false)->where('exitoso', true)->count(),
            'creados'       => (clone $base)->where('accion', 'crear')->where('exitoso', true)->count(),
            'errores'       => (clone $base)->where('exitoso', false)->count(),
            'hoy'           => (clone $base)->whereDate('created_at', today())->count(),
        ];

        $logActual = $this->verLogId
            ? DB::table('cliente_erp_lookups')->where('id', $this->verLogId)->first()
            : null;

        return view('livewire.integraciones.clientes-erp', compact('logs', 'integraciones', 'stats', 'logActual'));
    }

    public function verLog(int $id): void { $this->verLogId = $id; }
    public function cerrarLog(): void { $this->verLogId = null; }

    public function aplicarFiltro(string $estado): void
    {
        $this->filtroEstado = $estado;
        $this->resetPage();
    }
}
