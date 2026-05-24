<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * 🚫 Si el tenant tiene suspendido_por_mora=true, comparte el flag a la vista
 * Y bloquea acciones POST/PUT/DELETE (las lecturas GET las permitimos para
 * que el sidebar se vea normal pero todo lo "interactivo" queda inhabilitado).
 *
 * El sidebar/topbar leen `view()->shared('tenant_moroso')` para mostrar
 * los items en gris/disabled y un banner persistente "Tu suscripción venció,
 * paga para reactivar".
 *
 * Cuando el cliente intenta hacer cualquier acción (POST formulario, click
 * Livewire, AJAX), se le devuelve 402 (Payment Required) con redirect a
 * /billing/expirado.
 *
 * Rutas siempre permitidas:
 *  - /login, /logout, /register
 *  - /billing/*  (página de pago + retorno Wompi)
 *  - /api/saas-billing/wompi/webhook
 */
class BloqueaSiMoroso
{
    public function handle(Request $request, Closure $next)
    {
        $rutaActual = $request->path();
        $whitelist  = [
            'login', 'logout', 'register',
            'billing/expirado', 'billing/gracias',
            'api/saas-billing/wompi/webhook',
            'livewire/update', // permitir el banner Livewire si se renderea
        ];

        $esWhitelist = false;
        foreach ($whitelist as $w) {
            if ($rutaActual === $w || str_starts_with($rutaActual, $w . '/')) {
                $esWhitelist = true;
                break;
            }
        }

        $tenant = app(TenantManager::class)->current();
        $moroso = $tenant && $tenant->suspendido_por_mora;

        // Compartir flag con todas las vistas para que sidebar/topbar reaccionen
        view()->share('tenantMoroso', $moroso);

        if (!$moroso || $esWhitelist) {
            return $next($request);
        }

        // ⚠️ Bloqueo de acciones (POST/PUT/PATCH/DELETE) — lecturas GET las permitimos.
        // Esto deja al cliente navegar el sidebar pero NO puede crear/editar/borrar nada.
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                return response()->json([
                    'error'    => 'subscription_expired',
                    'message'  => 'Tu suscripción está vencida. Paga para reactivar las funciones.',
                    'redirect' => route('billing.expirado'),
                ], 402);
            }
            return redirect()->route('billing.expirado');
        }

        return $next($request);
    }
}
