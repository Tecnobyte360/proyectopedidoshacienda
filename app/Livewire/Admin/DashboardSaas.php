<?php

namespace App\Livewire\Admin;

use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DashboardSaas extends Component
{
    public function mount(): void {}

    private function tm(): TenantManager
    {
        return app(TenantManager::class);
    }

    /** MRR = suma del monto mensualizado de todas las suscripciones activas */
    #[Computed]
    public function mrr(): float
    {
        return (float) $this->tm()->withoutTenant(function () {
            $sus = Suscripcion::whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
                ->whereNotNull('monto')->get();
            $total = 0;
            foreach ($sus as $s) {
                $monto = (float) $s->monto;
                if ($s->ciclo === Suscripcion::CICLO_ANUAL) $monto = $monto / 12;
                $total += $monto;
            }
            return $total;
        });
    }

    #[Computed]
    public function kpis(): array
    {
        return $this->tm()->withoutTenant(function () {
            $tenants    = Tenant::count();
            $activos    = Tenant::where('activo', true)->count();
            $suspendidos= Tenant::where('activo', false)->count();
            $trial      = Suscripcion::where('estado', Suscripcion::ESTADO_TRIAL)->count();

            $ingresoMes = (float) Pago::where('estado', Pago::ESTADO_CONFIRMADO)
                ->where('fecha_pago', '>=', now()->startOfMonth())->sum('monto');
            $ingresoMesPasado = (float) Pago::where('estado', Pago::ESTADO_CONFIRMADO)
                ->whereBetween('fecha_pago', [
                    now()->subMonth()->startOfMonth(),
                    now()->subMonth()->endOfMonth(),
                ])->sum('monto');

            $delta = $ingresoMesPasado > 0
                ? round((($ingresoMes - $ingresoMesPasado) / $ingresoMesPasado) * 100, 1)
                : null;

            $pendientes = Pago::where('estado', Pago::ESTADO_PENDIENTE)->sum('monto');
            $morosos = Suscripcion::where('fecha_fin', '<', now()->toDateString())
                ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_EXPIRADA])
                ->count();

            return compact(
                'tenants', 'activos', 'suspendidos', 'trial',
                'ingresoMes', 'ingresoMesPasado', 'delta',
                'pendientes', 'morosos'
            );
        });
    }

    /** Ingresos últimos 12 meses por mes */
    #[Computed]
    public function serie12meses(): array
    {
        return $this->tm()->withoutTenant(function () {
            $rows = Pago::query()
                ->where('estado', Pago::ESTADO_CONFIRMADO)
                ->where('fecha_pago', '>=', now()->subMonths(12)->startOfMonth())
                ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto) AS total")
                ->groupBy('mes')
                ->orderBy('mes')
                ->pluck('total', 'mes')
                ->toArray();

            // Rellenar meses faltantes con 0
            $serie = [];
            for ($i = 11; $i >= 0; $i--) {
                $mes = now()->subMonths($i)->format('Y-m');
                $serie[$mes] = (float) ($rows[$mes] ?? 0);
            }
            return $serie;
        });
    }

    #[Computed]
    public function porPlan()
    {
        return $this->tm()->withoutTenant(function () {
            return Plan::query()
                ->withCount(['suscripciones as activas_count' => function ($q) {
                    $q->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL]);
                }])
                ->orderByDesc('activas_count')
                ->get();
        });
    }

    #[Computed]
    public function proximosVencimientos()
    {
        return $this->tm()->withoutTenant(function () {
            return Suscripcion::with('tenant', 'plan')
                ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
                ->whereBetween('fecha_fin', [now()->toDateString(), now()->addDays(15)->toDateString()])
                ->orderBy('fecha_fin')
                ->limit(10)
                ->get();
        });
    }

    public function render()
    {
        return view('livewire.admin.dashboard-saas')->layout('layouts.app');
    }
}
