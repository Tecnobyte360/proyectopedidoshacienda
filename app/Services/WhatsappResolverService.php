<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    /**
     * Credenciales con jerarquía:
     *   1. Tenant.whatsapp_config (si tiene email+password) → tiene su propia cuenta
     *   2. ConfiguracionPlataforma.whatsapp_admin_* → superadmin centralizado (típico)
     *   3. .env WHATSAPP_API_EMAIL/PASSWORD → último recurso (legacy)
     */
    public function credenciales(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: app(TenantManager::class)->current();
        $config = $tenant?->whatsapp_config ?? [];

        // Si el tenant tiene email+password propios, los usa (overrides totales)
        if (!empty($config['email']) && !empty($config['password'])) {
            return [
                'email'        => $config['email'],
                'password'     => $config['password'],
                'api_base_url' => $config['api_base_url']
                                  ?? $this->plataformaApiBaseUrl()
                                  ?? 'https://wa-api.tecnobyteapp.com:1422',
            ];
        }

        // Fallback al superadmin centralizado en ConfiguracionPlataforma
        try {
            $cfg = \App\Models\ConfiguracionPlataforma::actual();
            if (!empty($cfg->whatsapp_admin_email) && !empty($cfg->whatsapp_admin_password)) {
                return [
                    'email'        => $cfg->whatsapp_admin_email,
                    'password'     => $cfg->whatsapp_admin_password,
                    'api_base_url' => $config['api_base_url']
                                      ?? ($cfg->whatsapp_api_base_url ?: 'https://wa-api.tecnobyteapp.com:1422'),
                ];
            }
        } catch (\Throwable $e) {
            // Sin tabla aún o error de lectura → caer a env
        }

        // Último recurso: .env
        return [
            'email'        => env('WHATSAPP_API_EMAIL'),
            'password'     => env('WHATSAPP_API_PASSWORD'),
            'api_base_url' => $config['api_base_url']
                              ?? 'https://wa-api.tecnobyteapp.com:1422',
        ];
    }

    private function plataformaApiBaseUrl(): ?string
    {
        try {
            return \App\Models\ConfiguracionPlataforma::actual()->whatsapp_api_base_url ?: null;
        } catch (\Throwable $e) { return null; }
    }

    /**
     * Cache key del token WhatsApp por tenant. Si el tenant usa las credenciales
     * de la plataforma (caso típico), el token CACHEADO es el mismo para todos
     * los tenants — usamos una sola key compartida en ese caso.
     */
    public function tokenCacheKey(?Tenant $tenant = null): string
    {
        $tenant = $tenant ?: app(TenantManager::class)->current();
        $config = $tenant?->whatsapp_config ?? [];

        // Si el tenant tiene email propio, key específica
        if (!empty($config['email'])) {
            return 'whatsapp_api_token_t' . ($tenant?->id ?? 'global');
        }

        // Si usa el superadmin centralizado, key compartida (más eficiente)
        return 'whatsapp_api_token_platform';
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
     * Obtiene un token JWT válido para llamar la API de TecnoByteApp.
     *
     * Flujo:
     *   1. Token cacheado válido → devolver.
     *   2. Refresh-token cacheado → POST /auth/refresh_token (liviano, NO
     *      invalida la sesión, evita rate-limit).
     *   3. Login fresh → guarda token (15 min) + refresh-token cookie (7 días).
     *
     * Mutex con Cache::lock para evitar que dos procesos hagan login en
     * paralelo (eso invalida sesiones mutuamente en TecnoByteApp).
     *
     * Centralizado aquí para que TODOS los callers (Chat, Monitor, Webhook,
     * StatusMedia) usen el mismo flujo y no machaquen /auth/login.
     */
    public function token(?Tenant $tenant = null, bool $forzarFresh = false): ?string
    {
        $cred = $this->credenciales($tenant);
        if (empty($cred['email']) || empty($cred['password'])) {
            return null;
        }

        $cacheKey   = $this->tokenCacheKey($tenant);
        $refreshKey = $cacheKey . '_refresh';
        $base       = rtrim($cred['api_base_url'], '/');

        // 1) Token cacheado válido
        if (!$forzarFresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') return $cached;
        }

        // Mutex: solo un proceso a la vez puede hacer login/refresh para esta key
        $lock = Cache::lock($cacheKey . '_lock', 10);
        try {
            if (!$lock->block(8)) {
                Log::warning('No se obtuvo lock de token, devuelvo cache actual');
                return Cache::get($cacheKey);
            }

            // Re-chequeo dentro del lock — quizás otro proceso ya lo refrescó
            if (!$forzarFresh) {
                $cached = Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') return $cached;
            }

            // 2) Refresh con cookie jrt
            $jrt = Cache::get($refreshKey);
            if (is_string($jrt) && $jrt !== '' && !$forzarFresh) {
                try {
                    $resp = Http::withoutVerifying()
                        ->withHeaders(['Cookie' => "jrt={$jrt}"])
                        ->timeout(15)
                        ->post("{$base}/auth/refresh_token");

                    if ($resp->successful() && $token = $resp->json('token')) {
                        Cache::put($cacheKey, $token, now()->addMinutes(13));
                        $this->guardarRefreshDeCookies($resp, $refreshKey);
                        Log::info('🔁 token refrescado', ['cache_key' => $cacheKey]);
                        return $token;
                    }
                    Log::warning('refresh_token rechazado, cayendo a login', [
                        'status' => $resp->status(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('refresh_token excepción: ' . $e->getMessage());
                }
                Cache::forget($refreshKey);
            }

            // 3) Login fresh (último recurso)
            try {
                $resp = Http::withoutVerifying()
                    ->timeout(15)
                    ->post("{$base}/auth/login", [
                        'email'    => $cred['email'],
                        'password' => $cred['password'],
                    ]);

                $token = $resp->successful() ? $resp->json('token') : null;
                if (is_string($token) && $token !== '') {
                    Cache::put($cacheKey, $token, now()->addMinutes(13));
                    $this->guardarRefreshDeCookies($resp, $refreshKey);
                    Log::info('✅ login OK', ['cache_key' => $cacheKey]);
                    return $token;
                }

                Log::error('🔴 login falló', [
                    'cache_key' => $cacheKey,
                    'status'    => $resp->status(),
                    'body'      => mb_strimwidth((string) $resp->body(), 0, 300),
                ]);
                return null;
            } catch (\Throwable $e) {
                Log::error('🔴 login excepción: ' . $e->getMessage());
                return null;
            }
        } finally {
            optional($lock)->release();
        }
    }

    /** Extrae cookie jrt de la respuesta y la cachea */
    private function guardarRefreshDeCookies($response, string $refreshKey): void
    {
        try {
            foreach ($response->cookies()->toArray() as $c) {
                if (($c['Name'] ?? '') === 'jrt' && !empty($c['Value'])) {
                    Cache::put($refreshKey, $c['Value'], now()->addDays(7));
                    return;
                }
            }
        } catch (\Throwable $e) { /* ignorar */ }
    }

    /** Invalida el token cacheado (llamar cuando se detecta 401 ERR_SESSION_EXPIRED) */
    public function invalidarToken(?Tenant $tenant = null): void
    {
        $cacheKey = $this->tokenCacheKey($tenant);
        Cache::forget($cacheKey);
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
