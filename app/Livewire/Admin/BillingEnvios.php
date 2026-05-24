<?php

namespace App\Livewire\Admin;

use App\Models\SaasBillingEnvio;
use App\Models\Tenant;
use App\Services\TenantManager;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class BillingEnvios extends Component
{
    use WithPagination;

    public string $rango       = '7d';   // hoy | 7d | 30d | mes
    public string $filtroTipo  = '';     // factura | recordatorio | suspendido
    public string $filtroEtapa = '';
    public string $filtroOk    = '';     // '' | 1 | 0
    public string $filtroCanal = '';     // '' | whatsapp | email
    public ?int   $filtroTenant = null;
    public string $busqueda    = '';

    public function updating($prop): void
    {
        if (in_array($prop, ['rango', 'filtroTipo', 'filtroEtapa', 'filtroOk', 'filtroCanal', 'filtroTenant', 'busqueda'], true)) {
            $this->resetPage();
        }
    }

    private function ventana(): array
    {
        return match ($this->rango) {
            'hoy' => [now()->startOfDay(), now()->endOfDay()],
            '30d' => [now()->subDays(30), now()],
            'mes' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->subDays(7), now()],
        };
    }

    private function baseQuery()
    {
        [$desde, $hasta] = $this->ventana();
        return SaasBillingEnvio::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($this->filtroTipo, fn($q) => $q->where('tipo', $this->filtroTipo))
            ->when($this->filtroEtapa, fn($q) => $q->where('etapa', $this->filtroEtapa))
            ->when($this->filtroOk !== '', fn($q) => $q->where('ok', (bool) $this->filtroOk))
            ->when($this->filtroCanal, fn($q) => $q->where('canal', $this->filtroCanal))
            ->when($this->filtroTenant, fn($q) => $q->where('tenant_id', $this->filtroTenant))
            ->when($this->busqueda, function ($q) {
                $b = $this->busqueda;
                $q->where(function ($qq) use ($b) {
                    $qq->where('telefono', 'like', "%{$b}%")
                       ->orWhere('mensaje', 'like', "%{$b}%")
                       ->orWhere('error', 'like', "%{$b}%");
                });
            });
    }

    #[Computed]
    public function kpis(): array
    {
        $tm = app(TenantManager::class);
        return $tm->withoutTenant(function () {
            [$desde, $hasta] = $this->ventana();
            $base = SaasBillingEnvio::query()->whereBetween('created_at', [$desde, $hasta]);
            return [
                'total'        => (clone $base)->count(),
                'ok'           => (clone $base)->where('ok', true)->count(),
                'fallidos'     => (clone $base)->where('ok', false)->count(),
                'whatsapp'     => (clone $base)->where('canal', 'whatsapp')->count(),
                'emails'       => (clone $base)->where('canal', 'email')->count(),
                'facturas'     => (clone $base)->where('tipo', 'factura')->count(),
                'recordat'     => (clone $base)->where('tipo', 'recordatorio')->count(),
                'suspendido'   => (clone $base)->where('tipo', 'suspendido')->count(),
                'tenants_afct' => (clone $base)->distinct('tenant_id')->count('tenant_id'),
            ];
        });
    }

    #[Computed]
    public function envios()
    {
        return app(TenantManager::class)->withoutTenant(function () {
            return $this->baseQuery()
                ->with(['tenant:id,nombre,slug', 'pago:id,monto'])
                ->orderByDesc('id')
                ->paginate(25);
        });
    }

    #[Computed]
    public function tenants()
    {
        return app(TenantManager::class)->withoutTenant(
            fn () => Tenant::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function reintentar(int $envioId): void
    {
        $tm = app(TenantManager::class);
        $envio = $tm->withoutTenant(fn () => SaasBillingEnvio::find($envioId));
        if (!$envio) {
            $this->dispatch('reintento-resultado', [
                'ok'       => false,
                'telefono' => '',
                'intentos' => 0,
                'error'    => 'Envío no encontrado',
            ]);
            return;
        }

        $ok = $tm->withoutTenant(fn () => $envio->reintentar());
        $envio->refresh();

        unset($this->envios, $this->kpis);

        // Evento detallado para el modal SweetAlert
        $this->dispatch('reintento-resultado', [
            'ok'       => $ok,
            'telefono' => $envio->telefono,
            'intentos' => $envio->intentos,
            'error'    => $envio->error,
            'tenant'   => $envio->tenant?->nombre,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.billing-envios')->layout('layouts.app');
    }
}
