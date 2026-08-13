<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🔑 Autenticación de la API por TENANT. El sistema externo envía su api_key
 * (header X-API-KEY o Authorization: Bearer). Resolvemos el tenant dueño de esa
 * key y lo dejamos seteado en TenantManager, para que todo salga desde SU
 * número de WhatsApp / su config. Sin key válida → 401.
 */
class TenantApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-API-KEY')
            ?? $request->header('Authorization')
            ?? $request->query('api_key');

        if (is_string($provided) && str_starts_with($provided, 'Bearer ')) {
            $provided = trim(substr($provided, 7));
        }
        $provided = trim((string) $provided);

        if ($provided === '') {
            return response()->json(['ok' => false, 'message' => 'Falta la API key (header X-API-KEY).'], 401);
        }

        $tenant = Tenant::withoutGlobalScopes()
            ->whereNotNull('api_key')
            ->where('api_key', $provided)
            ->first();

        if (!$tenant) {
            return response()->json(['ok' => false, 'message' => 'API key inválida.'], 401);
        }

        // Dejar el tenant activo para el resto del request.
        app(TenantManager::class)->set($tenant);
        $request->attributes->set('tenant_api', $tenant);

        return $next($request);
    }
}
