<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestionar Estados de WhatsApp (Stories) a travs de la API
 * de EstradaHub / TecnoByteApp.
 *
 * Endpoints:
 *   GET    /status         -> listar estados activos
 *   POST   /status         -> crear estado (multipart: whatsappId, body, medias, scheduledFor)
 *   DELETE /status/:id     -> eliminar estado
 */
class WhatsappStatusService
{
    /**
     * Lista los estados activos del tenant actual.
     */
    public function listar(): array
    {
        $resp = $this->apiGet('/status');

        if (!$resp) {
            return [];
        }

        return $resp;
    }

    /**
     * Crea un nuevo estado de WhatsApp.
     *
     * @param int         $whatsappId   ID de la conexin en EstradaHub
     * @param string|null $body         Texto del estado (caption si hay media)
     * @param string|null $mediaPath    Ruta local del archivo a subir
     * @param string|null $scheduledFor Fecha ISO para programar (opcional)
     */
    public function crear(
        int $whatsappId,
        ?string $body = null,
        ?string $mediaPath = null,
        ?string $scheduledFor = null
    ): ?array {
        $resolver = app(WhatsappResolverService::class);
        $cred     = $resolver->credenciales();
        $token    = $this->obtenerToken($cred, $resolver->tokenCacheKey());

        if (!$token) {
            Log::error('WhatsappStatusService: no se pudo obtener token');
            return null;
        }

        $endpoint = rtrim($cred['api_base_url'], '/') . '/status';

        try {
            $request = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(30);

            // Construir multipart
            $multipart = [];

            $multipart[] = [
                'name'     => 'whatsappId',
                'contents' => (string) $whatsappId,
            ];

            if ($body) {
                $multipart[] = [
                    'name'     => 'body',
                    'contents' => $body,
                ];
            }

            if ($scheduledFor) {
                $multipart[] = [
                    'name'     => 'scheduledFor',
                    'contents' => $scheduledFor,
                ];
            }

            if ($mediaPath && file_exists($mediaPath)) {
                $multipart[] = [
                    'name'     => 'medias',
                    'contents' => fopen($mediaPath, 'r'),
                    'filename' => basename($mediaPath),
                ];
            }

            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(30)
                ->asMultipart()
                ->post($endpoint, $multipart);

            if ($resp->successful()) {
                Log::info('WhatsApp Status creado', [
                    'whatsappId' => $whatsappId,
                    'body'       => mb_substr($body ?? '', 0, 50),
                    'media'      => $mediaPath ? basename($mediaPath) : null,
                ]);
                return $resp->json();
            }

            // Retry con token nuevo si 401
            if ($resp->status() === 401) {
                $cacheKey = $resolver->tokenCacheKey();
                Cache::forget($cacheKey);
                $nuevoToken = $this->obtenerToken($cred, $cacheKey);
                if ($nuevoToken) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevoToken)
                        ->timeout(30)
                        ->asMultipart()
                        ->post($endpoint, $multipart);
                    if ($retry->successful()) {
                        return $retry->json();
                    }
                }
            }

            Log::error('WhatsappStatusService: error al crear estado', [
                'status' => $resp->status(),
                'body'   => mb_substr($resp->body(), 0, 500),
            ]);
            return null;

        } catch (\Throwable $e) {
            Log::error('WhatsappStatusService: excepcin: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina un estado por su ID en EstradaHub.
     */
    public function eliminar(int $statusId): bool
    {
        $resolver = app(WhatsappResolverService::class);
        $cred     = $resolver->credenciales();
        $token    = $this->obtenerToken($cred, $resolver->tokenCacheKey());

        if (!$token) return false;

        $endpoint = rtrim($cred['api_base_url'], '/') . "/status/{$statusId}";

        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->delete($endpoint);

            if ($resp->successful()) {
                Log::info("WhatsApp Status eliminado: {$statusId}");
                return true;
            }

            if ($resp->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $nuevo = $this->obtenerToken($cred, $resolver->tokenCacheKey());
                if ($nuevo) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevo)
                        ->timeout(15)
                        ->delete($endpoint);
                    return $retry->successful();
                }
            }

            Log::warning("WhatsappStatusService: no se pudo eliminar status {$statusId}", [
                'status' => $resp->status(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsappStatusService eliminar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista conexiones WhatsApp disponibles (CONNECTED) del tenant.
     */
    public function conexionesDisponibles(): array
    {
        $resolver = app(WhatsappResolverService::class);
        $cred     = $resolver->credenciales();
        $token    = $this->obtenerToken($cred, $resolver->tokenCacheKey());

        if (!$token) return [];

        try {
            $base = rtrim($cred['api_base_url'], '/');
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(10)
                ->get("{$base}/whatsapp/");

            if (!$resp->successful()) return [];

            $whatsapps = $resp->json('whatsapps', []);

            // Solo las conectadas
            return collect($whatsapps)
                ->filter(fn ($w) => strtoupper($w['status'] ?? '') === 'CONNECTED')
                ->map(fn ($w) => [
                    'id'          => (int) $w['id'],
                    'name'        => $w['name'] ?? 'Sin nombre',
                    'phoneNumber' => $w['phoneNumber'] ?? $w['phone_number'] ?? null,
                    'profileName' => $w['profileName'] ?? null,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('WhatsappStatusService: error listando conexiones: ' . $e->getMessage());
            return [];
        }
    }

    // ────────────────────── helpers de token ──────────────────────

    private function apiGet(string $path): ?array
    {
        $resolver = app(WhatsappResolverService::class);
        $cred     = $resolver->credenciales();
        $token    = $this->obtenerToken($cred, $resolver->tokenCacheKey());

        if (!$token) return null;

        $endpoint = rtrim($cred['api_base_url'], '/') . $path;

        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->get($endpoint);

            if ($resp->successful()) {
                return $resp->json();
            }

            if ($resp->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $nuevo = $this->obtenerToken($cred, $resolver->tokenCacheKey());
                if ($nuevo) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevo)
                        ->timeout(15)
                        ->get($endpoint);
                    if ($retry->successful()) {
                        return $retry->json();
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("WhatsappStatusService GET {$path}: " . $e->getMessage());
            return null;
        }
    }

    private function obtenerToken(array $cred, string $cacheKey): ?string
    {
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        if (empty($cred['email']) || empty($cred['password'])) return null;

        try {
            $endpoint = rtrim($cred['api_base_url'], '/') . '/auth/login';
            $resp = Http::withoutVerifying()
                ->timeout(15)
                ->post($endpoint, [
                    'email'    => $cred['email'],
                    'password' => $cred['password'],
                ]);

            if ($resp->successful()) {
                $token = $resp->json('token');
                if ($token) {
                    Cache::put($cacheKey, $token, now()->addMinutes(50));
                    return $token;
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsappStatusService login: ' . $e->getMessage());
        }

        return null;
    }
}
