<?php

namespace App\Livewire;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\SaasBilling\SaasBillingWompiService;
use App\Services\TenantManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * 🔔 Banner global de suscripción próxima a vencer.
 *
 * Aparece en el topbar/layout cuando el tenant tiene suscripción cerca de
 * vencer o ya vencida. Botón directo de pago via Wompi.
 *
 * Solo aparece para tenants (NO super-admin). El super-admin lo ve en su
 * propio dashboard /admin/dashboard.
 */
class SuscripcionBanner extends Component
{
    public bool $oculto = false; // dismiss por sesión

    /** ¿Cuándo mostrar el banner? */
    private const UMBRAL_DIAS = 10;

    public function mount(): void
    {
        $this->oculto = (bool) session('suscripcion_banner_oculto', false);
    }

    public function ocultar(): void
    {
        $this->oculto = true;
        session(['suscripcion_banner_oculto' => true]);
    }

    private function tenantActual(): ?Tenant
    {
        $tm = app(TenantManager::class);
        return $tm->current();
    }

    #[Computed]
    public function info(): ?array
    {
        $tenant = $this->tenantActual();
        if (!$tenant) return null;

        // Solo mostrar al usar la plataforma como tenant (con o sin impersonación)
        if (auth()->user()?->tenant_id === null && !session('tenant_imitado_id')) {
            return null; // super-admin sin impersonar
        }

        $tm = app(TenantManager::class);
        $sus = $tm->withoutTenant(function () use ($tenant) {
            return Suscripcion::with('plan')
                ->where('tenant_id', $tenant->id)
                ->whereIn('estado', [
                    Suscripcion::ESTADO_ACTIVA,
                    Suscripcion::ESTADO_TRIAL,
                    Suscripcion::ESTADO_EXPIRADA,
                ])
                ->orderByDesc('id')
                ->first();
        });

        if (!$sus || !$sus->fecha_fin) return null;

        $hoy  = now()->startOfDay();
        $dias = (int) $hoy->diffInDays($sus->fecha_fin->startOfDay(), false);

        // Solo mostrar si está dentro del umbral o vencida
        if ($dias > self::UMBRAL_DIAS) return null;

        // Buscar pago pendiente reciente
        $pago = $tm->withoutTenant(function () use ($tenant, $sus) {
            return Pago::where('tenant_id', $tenant->id)
                ->where('suscripcion_id', $sus->id)
                ->where('estado', Pago::ESTADO_PENDIENTE)
                ->orderByDesc('id')
                ->first();
        });

        // Severidad: verde (>3), amarillo (1-3), naranja (hoy), rojo (vencida)
        $sev = match (true) {
            $dias <  0  => 'rojo',
            $dias === 0 => 'naranja',
            $dias <= 3  => 'amarillo',
            default     => 'verde',
        };

        // Paleta unificada en rojito clarito — escala de intensidad por severidad
        $estilos = [
            'verde'    => [
                'bg'      => 'bg-red-50',
                'border'  => 'border-red-200',
                'text'    => 'text-red-700',
                'textSub' => 'text-red-600',
                'iconBg'  => 'bg-red-100 text-red-600',
                'btnBg'   => 'bg-red-500 hover:bg-red-600',
                'icon'    => 'fa-circle-info',
            ],
            'amarillo' => [
                'bg'      => 'bg-red-100',
                'border'  => 'border-red-300',
                'text'    => 'text-red-800',
                'textSub' => 'text-red-700',
                'iconBg'  => 'bg-red-200 text-red-700',
                'btnBg'   => 'bg-red-600 hover:bg-red-700',
                'icon'    => 'fa-triangle-exclamation',
            ],
            'naranja'  => [
                'bg'      => 'bg-red-100',
                'border'  => 'border-red-400',
                'text'    => 'text-red-900',
                'textSub' => 'text-red-800',
                'iconBg'  => 'bg-red-200 text-red-700',
                'btnBg'   => 'bg-red-600 hover:bg-red-700',
                'icon'    => 'fa-bell',
            ],
            'rojo'     => [
                'bg'      => 'bg-red-200',
                'border'  => 'border-red-500',
                'text'    => 'text-red-900',
                'textSub' => 'text-red-800',
                'iconBg'  => 'bg-red-300 text-red-800',
                'btnBg'   => 'bg-red-700 hover:bg-red-800',
                'icon'    => 'fa-circle-exclamation',
            ],
        ];

        $mensaje = match ($sev) {
            'rojo'     => abs($dias) === 1
                ? "Tu suscripción venció ayer."
                : "Tu suscripción venció hace " . abs($dias) . " días.",
            'naranja'  => "Tu suscripción vence HOY.",
            'amarillo' => "Tu suscripción vence en {$dias} día" . ($dias === 1 ? '' : 's') . ".",
            default    => "Tu suscripción vence en {$dias} días.",
        };

        $monto = (float) ($pago?->monto ?? $sus->monto ?? 0);

        return [
            'sev'       => $sev,
            'mensaje'   => $mensaje,
            'dias'      => $dias,
            'fecha_fin' => $sus->fecha_fin,
            'plan'      => $sus->plan?->nombre,
            'monto'     => $monto,
            'pago_id'   => $pago?->id,
            'link_pago' => $pago?->link_pago_url,
            'estilos'   => $estilos[$sev],
        ];
    }

    /** Genera (o reutiliza) link Wompi y devuelve URL en evento. */
    public function pagarAhora(): void
    {
        $info = $this->info;
        if (!$info) return;

        $tenant = $this->tenantActual();
        if (!$tenant) return;

        $tm = app(TenantManager::class);

        // Encontrar o crear el Pago pendiente
        $pago = $tm->withoutTenant(function () use ($tenant, $info) {
            $sus = Suscripcion::where('tenant_id', $tenant->id)->orderByDesc('id')->first();
            if (!$sus) return null;

            $pendiente = Pago::where('tenant_id', $tenant->id)
                ->where('suscripcion_id', $sus->id)
                ->where('estado', Pago::ESTADO_PENDIENTE)
                ->orderByDesc('id')
                ->first();

            if ($pendiente) return $pendiente;

            // Crear nuevo Pago al vuelo
            return Pago::create([
                'tenant_id'      => $tenant->id,
                'suscripcion_id' => $sus->id,
                'monto'          => (float) $info['monto'],
                'moneda'         => $sus->moneda ?: 'COP',
                'metodo'         => Pago::METODO_OTRO,
                'fecha_pago'     => now()->toDateString(),
                'cubre_desde'    => $sus->fecha_fin->toDateString(),
                'cubre_hasta'    => $sus->ciclo === Suscripcion::CICLO_ANUAL
                    ? $sus->fecha_fin->copy()->addYear()->toDateString()
                    : $sus->fecha_fin->copy()->addMonth()->toDateString(),
                'estado'         => Pago::ESTADO_PENDIENTE,
                'notas'          => 'Generado desde banner del tenant',
            ]);
        });

        if (!$pago) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay suscripción configurada para tu cuenta.']);
            return;
        }

        $url = app(SaasBillingWompiService::class)->generarLinkPago($pago);
        if (!$url) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Wompi no está configurado. Contacta a soporte: comercial@tecnobyte360.com',
            ]);
            return;
        }

        // Abrir la URL en pestaña nueva
        $this->dispatch('abrir-url', url: $url);
    }

    public function render()
    {
        return view('livewire.suscripcion-banner');
    }
}
