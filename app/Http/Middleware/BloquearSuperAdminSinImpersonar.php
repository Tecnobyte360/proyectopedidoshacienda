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
    public function handle(Request $request, Closure $next): Response
    {
        $u = auth()->user();

        if ($u && $u->tenant_id === null && $u->hasRole('super-admin')) {
            $impersonando = session()->has('tenant_imitado_id');
            if (!$impersonando) {
                return redirect()->route('admin.tenants.index')
                    ->with('warning', 'Como super-admin, debes usar "Ver como" en /admin/tenants para entrar al panel de un cliente específico.');
            }
        }

        return $next($request);
    }
}
