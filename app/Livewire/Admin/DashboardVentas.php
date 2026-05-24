<?php

namespace App\Livewire\Admin;

use App\Models\Pago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DashboardVentas extends Component
{
    public string $rango = '12m'; // 30d | 90d | 12m | ytd

    public function mount(): void {}

    private function tm(): TenantManager
    {
        return app(TenantManager::class);
    }

    private function ventana(): array
    {
        return match ($this->rango) {
            '30d'  => [now()->subDays(30), now()],
            '90d'  => [now()->subDays(90), now()],
            'ytd'  => [now()->startOfYear(), now()],
            default => [now()->subMonths(12)->startOfMonth(), now()],
        };
    }

    #[Computed]
    public function kpis(): array
    {
        return $this->tm()->withoutTenant(function () {
            [$desde, $hasta] = $this->ventana();
            $pagosOk = Pago::query()->where('estado', Pago::ESTADO_CONFIRMADO);
            $ingresosRango = (float) (clone $pagosOk)->whereBetween('fecha_pago', [$desde, $hasta])->sum('monto');
            $ingresosMes   = (float) (clone $pagosOk)->where('fecha_pago', '>=', now()->startOfMonth())->sum('monto');
            $ingresosMesP  = (float) (clone $pagosOk)->whereBetween('fecha_pago', [
                now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(),
            ])->sum('monto');
            $deltaPct = $ingresosMesP > 0 ? round((($ingresosMes - $ingresosMesP) / $ingresosMesP) * 100, 1) : null;

            $mrr = 0;
            foreach (Suscripcion::activas()->whereNotNull('monto')->get() as $s) {
                $m = (float) $s->monto;
                if ($s->ciclo === Suscripcion::CICLO_ANUAL) $m = $m / 12;
                $mrr += $m;
            }

            $pagosRangoCount = (clone $pagosOk)->whereBetween('fecha_pago', [$desde, $hasta])->count();
            $ticketPromedio  = $pagosRangoCount > 0 ? $ingresosRango / $pagosRangoCount : 0;

            $tenantsActivos = Tenant::where('activo', true)->count();
            $tenantsNuevos  = Tenant::whereBetween('created_at', [$desde, $hasta])->count();

            // Ingreso por día (gráfica de área)
            $serieDiaria = Pago::query()
                ->where('estado', Pago::ESTADO_CONFIRMADO)
                ->whereBetween('fecha_pago', [$desde, $hasta])
                ->selectRaw('DATE(fecha_pago) AS fecha, SUM(monto) AS total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->pluck('total', 'fecha')
                ->toArray();

            // Por método de pago
            $porMetodo = (clone $pagosOk)
                ->whereBetween('fecha_pago', [$desde, $hasta])
                ->selectRaw('metodo, SUM(monto) AS total, COUNT(*) AS cnt')
                ->groupBy('metodo')
                ->get();

            // Top tenants pagadores en el rango
            $topTenants = (clone $pagosOk)
                ->whereBetween('fecha_pago', [$desde, $hasta])
                ->selectRaw('tenant_id, SUM(monto) AS total, COUNT(*) AS pagos')
                ->groupBy('tenant_id')
                ->orderByDesc('total')
                ->limit(10)
                ->with('tenant:id,nombre,slug')
                ->get();

            // Por plan (donut)
            $porPlan = Plan::query()
                ->withCount(['suscripciones as activas_count' => function ($q) {
                    $q->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL]);
                }])
                ->orderByDesc('activas_count')
                ->get();

            return compact(
                'ingresosRango', 'ingresosMes', 'ingresosMesP', 'deltaPct',
                'mrr', 'ticketPromedio', 'pagosRangoCount',
                'tenantsActivos', 'tenantsNuevos',
                'serieDiaria', 'porMetodo', 'topTenants', 'porPlan',
                'desde', 'hasta'
            );
        });
    }

    public function render()
    {
        return view('livewire.admin.dashboard-ventas')->layout('layouts.app');
    }
}
