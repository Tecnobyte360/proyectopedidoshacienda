<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso si el request viene desde un subdominio de tenant.
 *
 * Uso típico: rutas de plataforma (/admin/tenants, /admin/planes, /roles, etc.)
 * que NUNCA deben ser accesibles desde la-hacienda.tecnobyte360.com aunque
 * el usuario logueado por accidente sea un super-admin.
 *
 * Doble seguridad junto con permission:roles.gestionar / tenants.gestionar.
 */
class SoloDominioPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantActivo = app(TenantManager::class)->current();

        if ($tenantActivo !== null) {
            abort(404);
        }

        return $next($request);
    }
}
