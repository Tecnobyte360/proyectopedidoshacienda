<?php

namespace App\Livewire\Monitoreo;

use App\Models\WhatsappBillingEvent;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CostosMeta extends Component
{
    public string $rango = 'mes'; // mes | 30d | 7d | hoy

    /** Tasa COP/USD aproximada — luego se puede traer de banco/cache */
    public float $cop_por_usd = 4150.0;

    /** 🏢 Tenant filtrado (null = ver todos los tenants agregados). Solo super-admin. */
    public ?int $tenantViewId = null;

    public function mount(): void
    {
        // Si NO es super-admin, fuerza el tenant del usuario actual (no le dejes elegir)
        $u = auth()->user();
        if ($u && !$u->hasRole('super-admin')) {
            $this->tenantViewId = $u->tenant_id;
        }
    }

    /** Listado de tenants para el dropdown (solo lo usa la vista cuando es super-admin). */
    public function getTenantesProperty()
    {
        return \App\Models\Tenant::query()
            ->withoutGlobalScopes()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    private function ventana(): array
    {
        return match ($this->rango) {
            'hoy'  => [now()->startOfDay(), now()->endOfDay()],
            '7d'   => [now()->subDays(7), now()],
            '30d'  => [now()->subDays(30), now()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /** Aplica el filtro de tenant a una query base (super-admin con selector). */
    private function scopeTenant($q)
    {
        $q->withoutGlobalScopes();
        if ($this->tenantViewId) {
            $q->where('tenant_id', $this->tenantViewId);
        }
        return $q;
    }

    #[Computed]
    public function kpis(): array
    {
        [$desde, $hasta] = $this->ventana();
        $base = $this->scopeTenant(WhatsappBillingEvent::query())
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where('billable', true);

        $totalUsd = (float) ((clone $base)->sum('cost_usd') ?: 0);
        $total    = (clone $base)->count();
        $service  = (clone $base)->where('categoria', 'service')->count();
        $utility  = (clone $base)->where('categoria', 'utility')->count();
        $market   = (clone $base)->where('categoria', 'marketing')->count();
        $auth     = (clone $base)->where('categoria', 'authentication')->count();

        return [
            'total_usd' => round($totalUsd, 4),
            'total_cop' => (int) round($totalUsd * $this->cop_por_usd),
            'total'     => $total,
            'service'   => $service,
            'utility'   => $utility,
            'marketing' => $market,
            'auth'      => $auth,
            'desde'     => $desde,
            'hasta'     => $hasta,
        ];
    }

    #[Computed]
    public function topPlantillas()
    {
        [$desde, $hasta] = $this->ventana();
        return $this->scopeTenant(WhatsappBillingEvent::query())
            ->selectRaw('origin_type, COUNT(*) AS cnt, SUM(cost_usd) AS usd')
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where('billable', true)
            ->groupBy('origin_type')
            ->orderByDesc('usd')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function topClientes()
    {
        [$desde, $hasta] = $this->ventana();
        return $this->scopeTenant(WhatsappBillingEvent::query())
            ->selectRaw('telefono, COUNT(*) AS cnt, SUM(cost_usd) AS usd')
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where('billable', true)
            ->whereNotNull('telefono')
            ->groupBy('telefono')
            ->orderByDesc('usd')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function serieDiaria()
    {
        [$desde, $hasta] = $this->ventana();
        $rows = $this->scopeTenant(WhatsappBillingEvent::query())
            ->selectRaw('DATE(ocurrido_at) AS fecha, categoria, COUNT(*) AS cnt, SUM(cost_usd) AS usd')
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where('billable', true)
            ->groupBy('fecha', 'categoria')
            ->orderBy('fecha')
            ->get();
        // Agregamos por día
        $porDia = [];
        foreach ($rows as $r) {
            $porDia[$r->fecha] = ($porDia[$r->fecha] ?? 0) + (float) $r->usd;
        }
        return $porDia;
    }

    public function render()
    {
        return view('livewire.monitoreo.costos-meta')->layout('layouts.app');
    }
}
