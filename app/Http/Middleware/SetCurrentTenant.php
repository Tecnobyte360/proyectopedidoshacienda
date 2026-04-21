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
    /**
     * Subdominios "del sistema" — NO son tenants, son rutas legítimas.
     * Cualquier OTRO subdominio que NO coincida con un tenant registrado
     * será rechazado con 404 (estricto).
     */
    private const SUBDOMINIOS_RESERVADOS = [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'pedidosonline',     // legacy de Hacienda — mantenido para no romper
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $manager = app(TenantManager::class);
        $tenant = null;

        // 1. Detectar por subdominio
        $sub = $this->extraerSubdominio($request);

        if ($sub !== null) {
            // Si es subdominio reservado del sistema, lo dejamos pasar sin tenant
            if (in_array($sub, self::SUBDOMINIOS_RESERVADOS, true)) {
                // No es un tenant, sigue normal
            } else {
                // Buscar tenant por slug; si NO existe → 404 estricto
                $tenant = Tenant::where('slug', $sub)->first();

                if (!$tenant) {
                    abort(404, "El subdominio '{$sub}' no está registrado. Verifica la URL o contacta a tu administrador.");
                }
            }
        }

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

    /**
     * Extrae el subdominio del host. Devuelve:
     *   - string (ej. "la-hacienda") si hay subdominio
     *   - null si es el dominio raíz o no aplica
     */
    private function extraerSubdominio(Request $request): ?string
    {
        $host = $request->getHost();
        $base = config('app.tenant_base_domain');

        if (!$base) return null;

        // Si es exactamente el dominio base, no hay subdominio
        if ($host === $base) return null;

        // Si termina en .{base}, extraer el subdominio
        if (str_ends_with($host, '.' . $base)) {
            $sub = substr($host, 0, -strlen('.' . $base));
            return $sub !== '' ? strtolower($sub) : null;
        }

        // Host no coincide con el dominio base (puede ser una IP o dominio custom)
        return null;
    }
}
