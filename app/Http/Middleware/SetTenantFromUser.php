<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🏢 Deja activo el tenant del usuario autenticado (token Sanctum), para que la
 * API opere sobre SU empresa. Va después de `auth:sanctum`.
 */
class SetTenantFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || empty($user->tenant_id)) {
            return response()->json([
                'ok' => false,
                'message' => 'El token no está asociado a una empresa (tenant).',
            ], 403);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['ok' => false, 'message' => 'Empresa no encontrada.'], 403);
        }

        app(TenantManager::class)->set($tenant);

        return $next($request);
    }
}
