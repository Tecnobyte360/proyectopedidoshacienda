<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * 🔐 Si el tenant tiene `requiere_2fa = true`, todos sus usuarios deben tener
 * 2FA activado. Damos N días de gracia desde que se activó la política.
 *
 * Después del período de gracia: bloquea TODAS las rutas excepto:
 *   - logout
 *   - /perfil/seguridad (para que pueda activarlo)
 *   - rutas Livewire (para que la propia config funcione)
 *
 * El usuario tendrá que activar 2FA o no podrá usar nada.
 */
class Forzar2FA
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) return $next($request);

        // Si ya tiene 2FA o no pertenece a un tenant, pasa de largo
        if (method_exists($user, 'tieneDosFactor') && $user->tieneDosFactor()) {
            return $next($request);
        }

        $tenant = app(TenantManager::class)->current();
        $forzadoPorTenant  = $tenant && $tenant->requiere_2fa;
        $forzadoPorUsuario = (bool) ($user->requiere_2fa ?? false);

        // Si ni el tenant ni el usuario individual lo exigen → pasa de largo
        if (!$forzadoPorTenant && !$forzadoPorUsuario) {
            return $next($request);
        }

        // Determinar fecha+gracia desde la que comenzó la obligación.
        // ⚠️ El forzado INDIVIDUAL siempre tiene prioridad: si el admin marcó
        // a un usuario específico, NO hay gracia (debe configurar 2FA ya).
        if ($forzadoPorUsuario) {
            $desde      = $user->requiere_2fa_desde ?? null;
            $diasGracia = 0;
        } else {
            $desde      = $tenant->requiere_2fa_desde ?? null;
            $diasGracia = $tenant->gracia_2fa_dias ?? 3;
        }

        // Verificar gracia: si requiere_2fa_desde es muy reciente, dejamos pasar
        if ($desde) {
            $deadline = $desde->copy()->addDays($diasGracia);
            if (now()->lt($deadline)) {
                // Compartir flag con vistas para mostrar banner de aviso
                view()->share('aviso_2fa_gracia', [
                    'deadline' => $deadline,
                    'dias_restantes' => (int) now()->diffInDays($deadline, false),
                ]);
                return $next($request);
            }
        }

        // Whitelist rutas que SIEMPRE puede acceder
        $ruta = $request->path();
        $whitelist = [
            'login', 'logout',
            'perfil/seguridad',
            'two-factor-challenge',
            'livewire/update',
            'livewire/upload-file',
            'livewire/message',
        ];
        foreach ($whitelist as $w) {
            if ($ruta === $w || str_starts_with($ruta, $w . '/')) {
                return $next($request);
            }
        }

        // Para Livewire/AJAX: 403 con redirect
        if ($request->expectsJson() || $request->header('X-Livewire')) {
            return response()->json([
                'error'    => '2fa_required',
                'message'  => 'Tu organización requiere autenticación en 2 pasos. Actívala para continuar.',
                'redirect' => route('perfil.seguridad'),
            ], 403);
        }

        return redirect()->route('perfil.seguridad')
            ->with('warning', '⚠️ Tu organización requiere autenticación en 2 pasos. Actívala para acceder al resto de funciones.');
    }
}
