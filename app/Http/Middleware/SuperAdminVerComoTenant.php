<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware "Ver como tenant" — solo para super-admin.
 *
 * Si la URL trae ?as_tenant=X y el usuario es super-admin:
 *  - Setea TenantManager para que TODAS las queries de esa request retornen
 *    los datos del tenant X.
 *  - NO toca session('tenant_imitado_id'), por lo que sidebar/topbar
 *    siguen en modo Super Admin.
 *
 * Resultado: el super-admin ve los datos de cualquier tenant en páginas
 * de plataforma sin entrar al rol del tenant.
 */
class SuperAdminVerComoTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = auth()->user();
        $asTenant = $request->query('as_tenant');

        if ($u && $u->hasRole('super-admin') && $asTenant) {
            $tenant = \App\Models\Tenant::query()
                ->withoutGlobalScopes()
                ->find($asTenant);

            if ($tenant) {
                app(TenantManager::class)->set($tenant);
                $request->attributes->set('viewing_as_tenant', $tenant);

                // 🔁 Limpiar caches que podrían tener datos del tenant anterior
                \Cache::forget('config_bot_actual_' . $tenant->id);
                \Cache::forget('config_bot_actual_global');

                \Log::info('👁️ SuperAdmin viendo como tenant', [
                    'user_id'  => $u->id,
                    'as_tenant'=> $asTenant,
                    'tenant'   => $tenant->slug,
                    'url'      => $request->fullUrl(),
                ]);
            } else {
                \Log::warning('SuperAdminVerComoTenant: tenant no encontrado', ['as_tenant' => $asTenant]);
            }
        }

        return $next($request);
    }
}
