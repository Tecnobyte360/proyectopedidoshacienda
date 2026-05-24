<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Services\SaasBilling\SaasBillingWompiService;
use App\Services\TenantManager;
use Illuminate\Http\Request;

class BillingPublicController extends Controller
{
    /**
     * 🚫 Pantalla de "suscripción vencida" — bloquea todas las funciones
     * pero permite al cliente ver el monto y pagar para reactivar.
     */
    public function expirado(SaasBillingWompiService $wompi)
    {
        $tenant = app(TenantManager::class)->current();
        if (!$tenant) {
            return redirect('/');
        }

        // Si NO está moroso, redirigir al dashboard
        if (!$tenant->suspendido_por_mora) {
            return redirect('/pedidos');
        }

        $tm = app(TenantManager::class);
        $suscripcion = $tm->withoutTenant(function () use ($tenant) {
            return Suscripcion::with('plan')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->first();
        });

        $pago = $tm->withoutTenant(function () use ($tenant, $suscripcion) {
            if (!$suscripcion) return null;
            return Pago::where('tenant_id', $tenant->id)
                ->where('suscripcion_id', $suscripcion->id)
                ->where('estado', Pago::ESTADO_PENDIENTE)
                ->orderByDesc('id')
                ->first();
        });

        // Si no hay pago pendiente, creamos uno al vuelo
        if (!$pago && $suscripcion) {
            $pago = $tm->withoutTenant(function () use ($tenant, $suscripcion) {
                return Pago::create([
                    'tenant_id'      => $tenant->id,
                    'suscripcion_id' => $suscripcion->id,
                    'monto'          => $suscripcion->monto,
                    'moneda'         => $suscripcion->moneda ?: 'COP',
                    'metodo'         => Pago::METODO_OTRO,
                    'fecha_pago'     => now()->toDateString(),
                    'cubre_desde'    => $suscripcion->fecha_fin?->toDateString() ?: now()->toDateString(),
                    'cubre_hasta'    => $suscripcion->ciclo === Suscripcion::CICLO_ANUAL
                        ? ($suscripcion->fecha_fin?->copy()->addYear() ?? now()->addYear())->toDateString()
                        : ($suscripcion->fecha_fin?->copy()->addMonth() ?? now()->addMonth())->toDateString(),
                    'estado'         => Pago::ESTADO_PENDIENTE,
                    'notas'          => 'Generado automáticamente desde pantalla de expirado',
                ]);
            });
        }

        // Asegurar link Wompi
        $linkPago = $pago?->link_pago_url;
        if ($pago && !$linkPago) {
            $linkPago = $wompi->generarLinkPago($pago);
        }

        $diasMora = $suscripcion?->fecha_fin
            ? max(0, (int) now()->startOfDay()->diffInDays($suscripcion->fecha_fin->startOfDay(), false) * -1)
            : 0;

        return view('billing.expirado', compact('tenant', 'suscripcion', 'pago', 'linkPago', 'diasMora'));
    }

    /**
     * 🎉 Página pública a donde Wompi redirige al tenant después de pagar.
     * Wompi añade query strings ?id=...&env=... con el id de la transacción.
     *
     * Esta página NO requiere login (el tenant no es usuario del SaaS,
     * solo recibió un link y pagó). Si la tx vino con id, hacemos polling
     * a Wompi para mostrar el estado real (sin esperar el webhook).
     */
    public function gracias(Request $request, SaasBillingWompiService $svc)
    {
        $txId = $request->query('id');
        $reference = null;
        $estado = 'pendiente';
        $pago = null;

        if ($txId) {
            // Polling rápido para confirmar el estado en vivo
            try {
                $cfg = \App\Models\ConfiguracionPlataforma::actual();
                $cred = $cfg->wompiSaasCredenciales();
                if ($cred && !empty($cred['private_key'])) {
                    $base = $cred['modo'] === 'produccion'
                        ? 'https://production.wompi.co/v1'
                        : 'https://sandbox.wompi.co/v1';
                    $resp = \Illuminate\Support\Facades\Http::withToken($cred['private_key'])
                        ->timeout(10)
                        ->get("{$base}/transactions/{$txId}");
                    if ($resp->successful()) {
                        $tx = $resp->json('data', []);
                        $reference = $tx['reference'] ?? null;
                        $statusWompi = $tx['status'] ?? 'PENDING';
                        // Si está aprobada y aún no se procesó (webhook lento), lo procesamos aquí
                        if ($statusWompi === 'APPROVED' && $reference) {
                            $svc->procesarAprobacion($tx);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($reference) {
            $pago = Pago::where('wompi_reference', $reference)->with('tenant', 'suscripcion.plan')->first();
            $estado = $pago?->estado ?? 'pendiente';
        }

        return view('billing.gracias', compact('pago', 'estado', 'txId'));
    }
}
