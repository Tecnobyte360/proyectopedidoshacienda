<?php

namespace App\Livewire\Admin\Suscripciones;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $busqueda      = '';
    public string $filtroEstado  = 'todas';

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public ?int   $tenant_id  = null;
    public ?int   $plan_id    = null;
    public string $estado     = Suscripcion::ESTADO_ACTIVA;
    public string $ciclo      = Suscripcion::CICLO_MENSUAL;
    public float  $monto      = 0;
    public string $moneda     = 'COP';
    public ?string $fecha_inicio = null;
    public ?string $fecha_fin    = null;
    public string $notas      = '';

    public function updatingBusqueda(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_fin    = now()->addMonth()->toDateString();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $s = app(TenantManager::class)->withoutTenant(fn () => Suscripcion::findOrFail($id));

        $this->editandoId   = $s->id;
        $this->tenant_id    = $s->tenant_id;
        $this->plan_id      = $s->plan_id;
        $this->estado       = $s->estado;
        $this->ciclo        = $s->ciclo;
        $this->monto        = (float) $s->monto;
        $this->moneda       = $s->moneda;
        $this->fecha_inicio = $s->fecha_inicio?->format('Y-m-d');
        $this->fecha_fin    = $s->fecha_fin?->format('Y-m-d');
        $this->notas        = (string) $s->notas;

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    /**
     * Cuando cambia el plan o ciclo, auto-completa el monto.
     */
    public function updatedPlanId(): void { $this->autoCompletarMonto(); }
    public function updatedCiclo(): void  { $this->autoCompletarMonto(); }

    private function autoCompletarMonto(): void
    {
        if (!$this->plan_id) return;
        $plan = Plan::find($this->plan_id);
        if (!$plan) return;
        $this->monto = $this->ciclo === Suscripcion::CICLO_ANUAL ? (float) $plan->precio_anual : (float) $plan->precio_mensual;

        // Auto-ajustar fecha fin
        if ($this->fecha_inicio) {
            $inicio = Carbon::parse($this->fecha_inicio);
            $this->fecha_fin = $this->ciclo === Suscripcion::CICLO_ANUAL
                ? $inicio->copy()->addYear()->toDateString()
                : $inicio->copy()->addMonth()->toDateString();
        }
    }

    protected function rules(): array
    {
        return [
            'tenant_id'    => 'required|exists:tenants,id',
            'plan_id'      => 'required|exists:planes,id',
            'estado'       => 'required|string|max:20',
            'ciclo'        => 'required|in:mensual,anual',
            'monto'        => 'numeric|min:0',
            'moneda'       => 'required|string|max:5',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'notas'        => 'nullable|string|max:1000',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();

        app(TenantManager::class)->withoutTenant(function () use ($data) {
            // Si es nueva, las viejas activas del mismo tenant pasan a "expiradas"
            if (!$this->editandoId) {
                Suscripcion::where('tenant_id', $data['tenant_id'])
                    ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
                    ->update(['estado' => Suscripcion::ESTADO_EXPIRADA]);
            }

            $s = Suscripcion::updateOrCreate(['id' => $this->editandoId], $data);

            // Reflejar fecha_fin en el tenant
            $tenant = Tenant::find($data['tenant_id']);
            if ($tenant) {
                $tenant->update([
                    'subscription_ends_at' => $s->fecha_fin,
                    'activo' => $s->estado === Suscripcion::ESTADO_ACTIVA || $s->estado === Suscripcion::ESTADO_TRIAL,
                ]);
            }
        });

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Suscripción actualizada.' : 'Suscripción creada.',
        ]);
    }

    public function cancelar(int $id): void
    {
        app(TenantManager::class)->withoutTenant(function () use ($id) {
            $s = Suscripcion::find($id);
            if (!$s) return;
            $s->update([
                'estado'             => Suscripcion::ESTADO_CANCELADA,
                'fecha_cancelacion'  => now()->toDateString(),
            ]);
            // Suspender tenant
            Tenant::where('id', $s->tenant_id)->update(['activo' => false]);
        });

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Suscripción cancelada y tenant suspendido.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId   = null;
        $this->tenant_id    = null;
        $this->plan_id      = null;
        $this->estado       = Suscripcion::ESTADO_ACTIVA;
        $this->ciclo        = Suscripcion::CICLO_MENSUAL;
        $this->monto        = 0;
        $this->moneda       = 'COP';
        $this->fecha_inicio = null;
        $this->fecha_fin    = null;
        $this->notas        = '';
        $this->resetValidation();
    }

    public function render()
    {
        $tm = app(TenantManager::class);

        $suscripciones = $tm->withoutTenant(function () {
            return Suscripcion::with(['tenant', 'plan'])
                ->when($this->filtroEstado !== 'todas', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->busqueda, function ($q) {
                    $q->whereHas('tenant', fn ($qq) => $qq->where('nombre', 'like', "%{$this->busqueda}%"));
                })
                ->orderByDesc('id')
                ->paginate(15);
        });

        $kpis = $tm->withoutTenant(function () {
            $activas = Suscripcion::where('estado', Suscripcion::ESTADO_ACTIVA)->get();
            $mrr = $activas->sum(function ($s) {
                return $s->ciclo === Suscripcion::CICLO_ANUAL ? $s->monto / 12 : $s->monto;
            });

            return [
                'total'       => Suscripcion::count(),
                'activas'     => $activas->count(),
                'mrr'         => $mrr,                                 // ingreso recurrente mensual
                'arr'         => $mrr * 12,                            // ingreso recurrente anual
                'por_vencer'  => Suscripcion::where('estado', Suscripcion::ESTADO_ACTIVA)
                    ->where('fecha_fin', '<=', now()->addDays(7))
                    ->count(),
                'vencidas'    => Suscripcion::where('estado', Suscripcion::ESTADO_ACTIVA)
                    ->where('fecha_fin', '<', now())
                    ->count(),
            ];
        });

        $tenants = $tm->withoutTenant(fn () => Tenant::orderBy('nombre')->get());
        $planes  = Plan::where('activo', true)->orderBy('orden')->get();

        return view('livewire.admin.suscripciones.index', compact('suscripciones', 'kpis', 'tenants', 'planes'))
            ->layout('layouts.app');
    }
}
