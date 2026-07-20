<?php

namespace App\Facturacion\Http\Middleware;

use App\Facturacion\Models\FeConfiguracion;
use App\Facturacion\Support\FeModulo;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica al SOFTWARE externo que consume la API de facturación.
 *
 * El emisor se identifica con su API key (header `Authorization: Bearer <key>`
 * o `X-Api-Key`). Esa key mapea a un tenant emisor (FeConfiguracion). Además
 * exige que el tenant tenga el PAQUETE de facturación electrónica en su plan.
 *
 * Deja disponibles en el request:
 *   $request->attributes->get('fe_config')  → FeConfiguracion del emisor
 *   $request->attributes->get('fe_tenant_id') → int tenant_id
 * y fija el contexto de tenant para el resto del request.
 */
class FacturadorApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->bearerToken() ?: (string) $request->header('X-Api-Key', '');
        $key = trim($key);

        if ($key === '') {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Falta la API key del emisor (Authorization: Bearer <key>).',
            ], 401);
        }

        $config = FeConfiguracion::porApiKey($key);
        if (!$config) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'API key inválida o emisor inactivo.',
            ], 401);
        }

        if (!FeModulo::habilitado((int) $config->tenant_id)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'El plan del emisor no incluye el paquete de facturación electrónica.',
            ], 403);
        }

        // Fijar contexto de tenant para el resto del request.
        try {
            $tenant = $config->tenant()->first();
            if ($tenant) app(TenantManager::class)->set($tenant);
        } catch (\Throwable $e) { /* no bloquear por esto */ }

        $request->attributes->set('fe_config', $config);
        $request->attributes->set('fe_tenant_id', (int) $config->tenant_id);

        return $next($request);
    }
}
