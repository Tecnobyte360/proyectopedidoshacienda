<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Resuelve credenciales WhatsApp + conexión por tenant.
 *
 * El JSON `whatsapp_config` del tenant tiene esta forma:
 *   {
 *     "email":          "tenant@email.com",
 *     "password":       "tenant-password",
 *     "api_base_url":   "https://wa-api.tecnobyteapp.com:1422",   (opcional, default global)
 *     "connection_ids": [15, 28],   // IDs de TecnoByteApp que le pertenecen
 *   }
 *
 * Si el tenant NO tiene credenciales, hace fallback a las del `.env`
 * (útil para el tenant inicial / sandbox).
 */
class WhatsappResolverService
{
    /** Credenciales del tenant actual (o fallback) */
    public function credenciales(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: app(TenantManager::class)->current();
        $config = $tenant?->whatsapp_config ?? [];

        return [
            'email'        => $config['email']        ?? env('WHATSAPP_API_EMAIL'),
            'password'     => $config['password']     ?? env('WHATSAPP_API_PASSWORD'),
            'api_base_url' => $config['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422',
        ];
    }

    /**
     * Cache key del token WhatsApp por tenant.
     * Cada tenant tiene su propio token cacheado.
     */
    public function tokenCacheKey(?Tenant $tenant = null): string
    {
        $tenant = $tenant ?: app(TenantManager::class)->current();
        return 'whatsapp_api_token_t' . ($tenant?->id ?? 'global');
    }

    /**
     * Lista de connection_ids que pertenecen a un tenant.
     */
    public function connectionIdsDelTenant(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: app(TenantManager::class)->current();
        $config = $tenant?->whatsapp_config ?? [];
        return array_map('intval', $config['connection_ids'] ?? []);
    }

    /**
     * Dado un connection_id (que viene del webhook), encuentra a qué tenant pertenece.
     * Devuelve null si ningún tenant lo tiene asignado.
     *
     * Usa caché con la lista global de mapeos: connection_id => tenant_id.
     */
    public function tenantPorConnectionId(int $connectionId): ?Tenant
    {
        $mapa = Cache::remember('wa_connection_to_tenant_map', 300, function () {
            $resultado = [];
            // Saltar el global scope para iterar todos los tenants
            $tenants = app(TenantManager::class)->withoutTenant(function () {
                return Tenant::where('activo', true)->get();
            });

            foreach ($tenants as $t) {
                $config = $t->whatsapp_config ?? [];
                foreach (($config['connection_ids'] ?? []) as $cid) {
                    $resultado[(int) $cid] = $t->id;
                }
            }
            return $resultado;
        });

        $tenantId = $mapa[$connectionId] ?? null;
        if (!$tenantId) return null;

        return app(TenantManager::class)->withoutTenant(fn () => Tenant::find($tenantId));
    }

    /**
     * Limpia el caché del mapa (llamarlo cuando se edita un tenant).
     */
    public function limpiarCache(): void
    {
        Cache::forget('wa_connection_to_tenant_map');
    }
}
