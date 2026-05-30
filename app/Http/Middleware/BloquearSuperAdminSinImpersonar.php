<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el usuario es super-admin (tenant_id=NULL + rol super-admin) y NO está
 * impersonando ningún tenant, le bloqueamos las rutas operativas (pedidos,
 * clientes, productos, etc).
 *
 * El super-admin debe usar "Ver como" para entrar al panel de un tenant.
 * Esto evita ver datos mezclados de varios tenants y refuerza la separación.
 */
class BloquearSuperAdminSinImpersonar
{
    /**
     * Rutas de plataforma que el super-admin SÍ puede ver sin impersonar
     * (tienen su propio selector de tenant internamente).
     */
    private const RUTAS_PLATAFORMA = [
        'configuracion.bot',
        'configuracion.bot-lecciones',
        'configuracion.informes-negocio',
        'meta-whatsapp.index',
        'monitoreo.llm',
        'monitoreo.agente',
        'monitoreo.watchdog',
        'monitoreo.costos-meta',
        'monitoreo.llamadas',
        'alertas.index',
        // 👇 Gestión de usuarios/roles globales del SaaS
        'usuarios.index',
        'roles.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $u = auth()->user();

        if ($u && $u->tenant_id === null && $u->hasRole('super-admin')) {
            $impersonando = session()->has('tenant_imitado_id');

            // ✅ Bypass: si la ruta es de plataforma (con selector interno de tenant),
            // dejamos pasar al super-admin sin obligarlo a impersonar.
            $rutaActual = optional($request->route())->getName();
            if (!$impersonando && !in_array($rutaActual, self::RUTAS_PLATAFORMA, true)) {
                return redirect()->route('admin.tenants.index')
                    ->with('warning', 'Como super-admin, debes usar "Ver como" en /admin/tenants para entrar al panel de un cliente específico.');
            }
        }

        return $next($request);
    }
}
