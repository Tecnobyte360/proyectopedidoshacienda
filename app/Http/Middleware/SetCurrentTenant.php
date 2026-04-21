<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Determina y setea el tenant actual para el request.
 *
 * Estrategias (en orden):
 *  1. Subdominio: cliente.tecnobyte360.com → busca tenant por slug
 *  2. Sesión "tenant_imitado_id" del super-admin (impersonación)
 *  3. tenant_id del usuario logueado
 *
 * Si el usuario es super-admin sin tenant seleccionado, no setea (ve todo).
 * Si el tenant detectado está suspendido (activo=false) → 403.
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = app(TenantManager::class);
        $tenant = null;

        // 1. Por subdominio (futuro)
        $tenant = $this->detectarPorSubdominio($request);

        // 2. Por impersonación de super-admin
        if (!$tenant && session()->has('tenant_imitado_id')) {
            $tenant = Tenant::find(session('tenant_imitado_id'));
        }

        // 3. Por usuario logueado
        if (!$tenant && auth()->check()) {
            $user = auth()->user();
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            }
        }

        if ($tenant) {
            // Bloquear si el tenant no tiene acceso activo
            if (!$tenant->tieneAccesoActivo() && !auth()->user()?->isSuperAdmin()) {
                abort(403, "Tu cuenta está suspendida o tu suscripción venció. Contacta al soporte.");
            }
            $manager->set($tenant);
        }

        return $next($request);
    }

    private function detectarPorSubdominio(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $base = config('app.tenant_base_domain');

        if (!$base) return null;

        // Si es exactamente el dominio base (sin subdominio), no es un tenant
        if ($host === $base || $host === 'www.' . $base) {
            return null;
        }

        // Extraer subdominio (cliente.tecnobyte360.com → "cliente")
        if (str_ends_with($host, '.' . $base)) {
            $sub = substr($host, 0, -strlen('.' . $base));
            if ($sub && $sub !== 'www') {
                return Tenant::where('slug', $sub)->first();
            }
        }

        return null;
    }
}
