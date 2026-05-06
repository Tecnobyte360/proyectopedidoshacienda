<?php

namespace App\Livewire\Integraciones;

use App\Models\Integracion;
use App\Models\Pedido;
use App\Services\IntegracionExportService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ExportLogs extends Component
{
    use WithPagination;

    public string $filtroEstado = 'todos'; // todos / ok / error
    public ?int   $filtroIntegracion = null;
    public ?int   $verLogId = null;

    protected $paginationTheme = 'tailwind';

    public function render()
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();

        $q = DB::table('integracion_export_logs as l')
            ->leftJoin('integraciones as i', 'l.integracion_id', '=', 'i.id')
            ->leftJoin('pedidos as p', 'l.pedido_id', '=', 'p.id')
            ->select(
                'l.*',
                'i.nombre as integracion_nombre',
                'i.tipo as integracion_tipo',
                'p.cliente_nombre',
                'p.total as pedido_total',
                'p.estado as pedido_estado'
            )
            ->where('l.tenant_id', $tenantId)
            ->orderByDesc('l.id');

        if ($this->filtroEstado !== 'todos') {
            $q->where('l.estado', $this->filtroEstado);
        }
        if ($this->filtroIntegracion) {
            $q->where('l.integracion_id', $this->filtroIntegracion);
        }

        $logs = $q->paginate(20);

        $integraciones = Integracion::where('tenant_id', $tenantId)
            ->where('exporta_pedidos', true)
            ->get(['id', 'nombre']);

        $stats = [
            'total'  => DB::table('integracion_export_logs')->where('tenant_id', $tenantId)->count(),
            'ok'     => DB::table('integracion_export_logs')->where('tenant_id', $tenantId)->where('estado', 'ok')->count(),
            'error'  => DB::table('integracion_export_logs')->where('tenant_id', $tenantId)->where('estado', 'error')->count(),
            'hoy'    => DB::table('integracion_export_logs')->where('tenant_id', $tenantId)->whereDate('created_at', today())->count(),
        ];

        $logActual = $this->verLogId
            ? DB::table('integracion_export_logs')->where('id', $this->verLogId)->first()
            : null;

        return view('livewire.integraciones.export-logs', compact('logs', 'integraciones', 'stats', 'logActual'));
    }

    public function verLog(int $id): void
    {
        $this->verLogId = $id;
    }

    public function cerrarLog(): void
    {
        $this->verLogId = null;
    }

    public function reintentar(int $logId): void
    {
        $log = DB::table('integracion_export_logs')->where('id', $logId)->first();
        if (!$log) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Log no encontrado.']);
            return;
        }

        $pedido = Pedido::find($log->pedido_id);
        if (!$pedido) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El pedido ya no existe.']);
            return;
        }

        try {
            $res = app(IntegracionExportService::class)->exportarPedido($pedido);
            $exitos = collect($res['resultados'] ?? [])->filter(fn ($r) => ($r['estado'] ?? '') === 'ok')->count();

            if ($exitos > 0) {
                $this->dispatch('notify', ['type' => 'success', 'message' => "✓ Pedido #{$pedido->id} reexportado correctamente."]);
            } else {
                $this->dispatch('notify', ['type' => 'warning', 'message' => "El reintento se ejecutó pero ninguna integración respondió OK."]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '❌ Reintento falló: ' . $e->getMessage()]);
        }
    }

    public function aplicarFiltro(string $estado): void
    {
        $this->filtroEstado = $estado;
        $this->resetPage();
    }
}
