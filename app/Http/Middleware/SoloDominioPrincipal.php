<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso si el request viene desde un SUBDOMINIO de tenant
 * (ej: la-hacienda.tecnobyte360.com).
 *
 * Permite el acceso si:
 *   - Es el dominio raíz (tecnobyte360.com)
 *   - Es un subdominio reservado del sistema (pedidosonline, www, api, ...)
 *
 * Esto NO depende de la sesión de impersonación: aunque el super-admin esté
 * "viendo como" un cliente desde el dominio principal, las rutas de plataforma
 * siguen siendo accesibles. Solo se bloquean si entras desde la URL del cliente.
 *
 * Uso típico: rutas /admin/* y /roles que SOLO deben existir en el dominio
 * principal (pedidosonline.tecnobyte360.com).
 */
class SoloDominioPrincipal
{
    /**
     * Subdominios "del sistema" — son legítimos, no son tenants.
     * Mantener sincronizado con SetCurrentTenant::SUBDOMINIOS_RESERVADOS.
     */
    private const SUBDOMINIOS_RESERVADOS = [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'pedidosonline',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');

        // Dominio raíz exacto → permitir
        if ($host === $base) {
            return $next($request);
        }

        // Subdominio del dominio base
        if (str_ends_with($host, '.' . $base)) {
            $sub = strtolower(substr($host, 0, -strlen('.' . $base)));

            // Reservados → permitir
            if (in_array($sub, self::SUBDOMINIOS_RESERVADOS, true)) {
                return $next($request);
            }

            // Subdominio de tenant → 404
            abort(404);
        }

        // Host no reconocido (IP local, dominio custom, etc) → permitir por default
        return $next($request);
    }
}
