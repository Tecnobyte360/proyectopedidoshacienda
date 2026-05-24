<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * 🚫 Si el tenant tiene suspendido_por_mora=true, redirige a /billing/expirado.
 *
 * Permite TODAS estas rutas para que el cliente pueda pagar y salir:
 *  - /login, /logout
 *  - /billing/* (página de pago + retorno Wompi)
 *  - /api/saas-billing/wompi/webhook (recepción del webhook)
 *
 * Si paga → webhook reactiva → suspendido_por_mora=false → puede usar todo normal.
 */
class BloqueaSiMoroso
{
    public function handle(Request $request, Closure $next)
    {
        // Rutas siempre permitidas (login + pago + logout + assets)
        $rutaActual = $request->path();
        $whitelist  = [
            'login', 'logout', 'register',
            'billing/expirado', 'billing/gracias',
            'api/saas-billing/wompi/webhook',
        ];

        foreach ($whitelist as $w) {
            if ($rutaActual === $w || str_starts_with($rutaActual, $w . '/')) {
                return $next($request);
            }
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant && $tenant->suspendido_por_mora) {
            // Para peticiones AJAX/Livewire devolver 403 sino redirige
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                return response()->json([
                    'error' => 'subscription_expired',
                    'redirect' => route('billing.expirado'),
                ], 403);
            }
            return redirect()->route('billing.expirado');
        }

        return $next($request);
    }
}
