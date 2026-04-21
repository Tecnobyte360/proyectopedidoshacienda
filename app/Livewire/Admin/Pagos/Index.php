<?php

namespace App\Livewire\Admin\Pagos;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $busqueda     = '';
    public string $filtroEstado = 'todos';
    public string $filtroMetodo = 'todos';

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public ?int   $tenant_id        = null;
    public ?int   $suscripcion_id   = null;
    public float  $monto            = 0;
    public string $moneda           = 'COP';
    public string $metodo           = Pago::METODO_TRANSFERENCIA;
    public string $referencia       = '';
    public string $comprobante_url  = '';
    public ?string $fecha_pago      = null;
    public ?string $cubre_desde     = null;
    public ?string $cubre_hasta     = null;
    public string $estado           = Pago::ESTADO_CONFIRMADO;
    public string $notas            = '';
    public bool   $renovar_suscripcion = true;

    public function updatingBusqueda(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingFiltroMetodo(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->fecha_pago = now()->toDateString();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $p = app(TenantManager::class)->withoutTenant(fn () => Pago::findOrFail($id));

        $this->editandoId       = $p->id;
        $this->tenant_id        = $p->tenant_id;
        $this->suscripcion_id   = $p->suscripcion_id;
        $this->monto            = (float) $p->monto;
        $this->moneda           = $p->moneda;
        $this->metodo           = $p->metodo;
        $this->referencia       = (string) $p->referencia;
        $this->comprobante_url  = (string) $p->comprobante_url;
        $this->fecha_pago       = $p->fecha_pago?->format('Y-m-d');
        $this->cubre_desde      = $p->cubre_desde?->format('Y-m-d');
        $this->cubre_hasta      = $p->cubre_hasta?->format('Y-m-d');
        $this->estado           = $p->estado;
        $this->notas            = (string) $p->notas;
        $this->renovar_suscripcion = false;

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function updatedTenantId(): void
    {
        // Auto-seleccionar suscripción activa del tenant
        if (!$this->tenant_id) {
            $this->suscripcion_id = null;
            return;
        }
        $sus = app(TenantManager::class)->withoutTenant(function () {
            return Suscripcion::where('tenant_id', $this->tenant_id)
                ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
                ->orderByDesc('id')
                ->first();
        });
        if ($sus) {
            $this->suscripcion_id = $sus->id;
            $this->monto = (float) $sus->monto;
            $this->moneda = $sus->moneda;
            $this->cubre_desde = now()->toDateString();
            $this->cubre_hasta = $sus->ciclo === Suscripcion::CICLO_ANUAL
                ? now()->addYear()->toDateString()
                : now()->addMonth()->toDateString();
        }
    }

    protected function rules(): array
    {
        return [
            'tenant_id'      => 'required|exists:tenants,id',
            'suscripcion_id' => 'nullable|exists:suscripciones,id',
            'monto'          => 'required|numeric|min:0',
            'moneda'         => 'required|string|max:5',
            'metodo'         => 'required|string|max:20',
            'referencia'     => 'nullable|string|max:100',
            'comprobante_url'=> 'nullable|url|max:500',
            'fecha_pago'     => 'required|date',
            'cubre_desde'    => 'nullable|date',
            'cubre_hasta'    => 'nullable|date|after_or_equal:cubre_desde',
            'estado'         => 'required|string|max:20',
            'notas'          => 'nullable|string|max:1000',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['registrado_por'] = auth()->id();

        app(TenantManager::class)->withoutTenant(function () use ($data) {
            $pago = Pago::updateOrCreate(['id' => $this->editandoId], $data);

            // Si está confirmado y se solicita renovar, extender la suscripción
            if ($pago->estado === Pago::ESTADO_CONFIRMADO
                && $this->renovar_suscripcion
                && $pago->suscripcion_id
                && $pago->cubre_hasta) {

                $sus = Suscripcion::find($pago->suscripcion_id);
                if ($sus) {
                    $sus->update([
                        'fecha_fin' => $pago->cubre_hasta,
                        'estado'    => Suscripcion::ESTADO_ACTIVA,
                    ]);

                    // Reactivar el tenant si estaba suspendido
                    Tenant::where('id', $sus->tenant_id)->update([
                        'activo'               => true,
                        'subscription_ends_at' => $pago->cubre_hasta,
                    ]);
                }
            }
        });

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId
                ? '✓ Pago actualizado.'
                : '💰 Pago registrado.' . ($this->renovar_suscripcion ? ' Suscripción renovada.' : ''),
        ]);
    }

    public function eliminar(int $id): void
    {
        app(TenantManager::class)->withoutTenant(fn () => Pago::where('id', $id)->delete());
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Pago eliminado.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId       = null;
        $this->tenant_id        = null;
        $this->suscripcion_id   = null;
        $this->monto            = 0;
        $this->moneda           = 'COP';
        $this->metodo           = Pago::METODO_TRANSFERENCIA;
        $this->referencia       = '';
        $this->comprobante_url  = '';
        $this->fecha_pago       = null;
        $this->cubre_desde      = null;
        $this->cubre_hasta      = null;
        $this->estado           = Pago::ESTADO_CONFIRMADO;
        $this->notas            = '';
        $this->renovar_suscripcion = true;
        $this->resetValidation();
    }

    public function render()
    {
        $tm = app(TenantManager::class);

        $pagos = $tm->withoutTenant(function () {
            return Pago::with(['tenant', 'suscripcion.plan', 'registradoPor'])
                ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->filtroMetodo !== 'todos', fn ($q) => $q->where('metodo', $this->filtroMetodo))
                ->when($this->busqueda, fn ($q) => $q->whereHas('tenant', fn ($qq) =>
                    $qq->where('nombre', 'like', "%{$this->busqueda}%")))
                ->orderByDesc('fecha_pago')
                ->orderByDesc('id')
                ->paginate(20);
        });

        $kpis = $tm->withoutTenant(function () {
            $hoy = now()->toDateString();
            $inicioMes = now()->startOfMonth();
            $inicioAnio = now()->startOfYear();

            return [
                'hoy'        => Pago::where('estado', 'confirmado')->where('fecha_pago', $hoy)->sum('monto'),
                'mes'        => Pago::where('estado', 'confirmado')->where('fecha_pago', '>=', $inicioMes)->sum('monto'),
                'anio'       => Pago::where('estado', 'confirmado')->where('fecha_pago', '>=', $inicioAnio)->sum('monto'),
                'pendientes' => Pago::where('estado', 'pendiente')->count(),
            ];
        });

        $tenants = $tm->withoutTenant(fn () => Tenant::orderBy('nombre')->get());

        return view('livewire.admin.pagos.index', compact('pagos', 'kpis', 'tenants'))
            ->layout('layouts.app');
    }
}
