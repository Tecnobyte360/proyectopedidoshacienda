<?php

namespace App\Http\Controllers;

use App\Services\WhatsappResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proxy autenticado para servir las medias de los estados de WhatsApp
 * publicados en TecnoByteApp. La API devuelve solo el nombre del archivo
 * (ej. "1777130186267.jpg") y los endpoints reales requieren Bearer token,
 * así que no podemos exponerlos directamente al navegador.
 *
 * Esta ruta:
 *   1. Resuelve el token del tenant actual.
 *   2. Prueba varias rutas conocidas hasta encontrar la que devuelva el archivo.
 *   3. Cachea el binario por 24h (la vigencia del estado) bajo una clave
 *      que incluye tenant_id para no mezclar entre tenants.
 *   4. Devuelve los bytes con el Content-Type correcto.
 */
class WhatsappStatusMediaController extends Controller
{
    /**
     * Rutas candidatas dentro de la API de TecnoByteApp para descargar
     * la media de un estado. Probamos en orden y usamos la primera que
     * responda 200.
     */
    private const PATH_CANDIDATES = [
        '/public/{file}',
        '/uploads/{file}',
        '/media/{file}',
        '/files/{file}',
        '/static/{file}',
        '/public/uploads/{file}',
        '/public/media/{file}',
        '/public/status/{file}',
        '/status/media/{file}',
        '/status/{file}',
        '/whatsapp/media/{file}',
        '/whatsapp/files/{file}',
        '/api/media/{file}',
        '/api/files/{file}',
        '/api/public/{file}',
    ];

    public function __invoke(Request $request, string $filename)
    {
        $filename = basename($filename);
        if (!preg_match('/^[\w\-\.]+\.(jpg|jpeg|png|webp|mp4|gif)$/i', $filename)) {
            abort(400, 'Nombre de archivo inválido.');
        }

        $resolver = app(WhatsappResolverService::class);
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'global';

        $cacheKey   = "wa_status_media_t{$tenantId}_" . md5($filename);
        $cacheKeyMime = "{$cacheKey}_mime";

        $bytes = Cache::get($cacheKey);
        $mime  = Cache::get($cacheKeyMime);

        if (!$bytes) {
            [$bytes, $mime] = $this->descargar($filename, $resolver);
            if (!$bytes) {
                abort(404, 'No se pudo descargar la media.');
            }
            Cache::put($cacheKey, $bytes, now()->addHours(24));
            Cache::put($cacheKeyMime, $mime, now()->addHours(24));
        }

        return response($bytes, 200, [
            'Content-Type'        => $mime ?: 'application/octet-stream',
            'Content-Length'      => strlen($bytes),
            'Cache-Control'       => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function descargar(string $filename, WhatsappResolverService $resolver): array
    {
        $cred  = $resolver->credenciales();
        $base  = rtrim($cred['api_base_url'] ?? '', '/');
        if (!$base) return [null, null];

        $token = $this->obtenerToken($resolver);
        if (!$token) return [null, null];

        $intentos = [];

        foreach (self::PATH_CANDIDATES as $template) {
            $path = str_replace('{file}', $filename, $template);
            $url  = $base . $path;

            try {
                // Probamos primero CON token y, si da 401 o 403, también SIN token
                // (puede que la ruta sea pública)
                $resp = Http::withoutVerifying()->withToken($token)->timeout(15)->get($url);

                if ($resp->status() === 401) {
                    Cache::forget($resolver->tokenCacheKey());
                    $token = $this->obtenerToken($resolver);
                    if ($token) $resp = Http::withoutVerifying()->withToken($token)->timeout(15)->get($url);
                }

                if (in_array($resp->status(), [401, 403])) {
                    $resp = Http::withoutVerifying()->timeout(15)->get($url);
                }

                $mime = (string) $resp->header('Content-Type');
                $intentos[$path] = $resp->status() . ' (' . mb_strimwidth($mime, 0, 30, '…') . ')';

                if ($resp->successful() && (str_contains($mime, 'image/') || str_contains($mime, 'video/') || $mime === 'application/octet-stream')) {
                    Log::info('✅ WA status media descargada', ['url' => $url, 'mime' => $mime, 'size' => strlen($resp->body())]);
                    return [$resp->body(), $mime];
                }
            } catch (\Throwable $e) {
                $intentos[$path] = 'EXC ' . mb_strimwidth($e->getMessage(), 0, 50);
            }
        }

        Log::warning('WA status media: ningún candidato funcionó', [
            'filename' => $filename,
            'base'     => $base,
            'intentos' => $intentos,
        ]);
        return [null, null];
    }

    private function obtenerToken(WhatsappResolverService $resolver): ?string
    {
        $cacheKey = $resolver->tokenCacheKey();
        if ($t = Cache::get($cacheKey)) return $t;

        $cred = $resolver->credenciales();
        if (empty($cred['email']) || empty($cred['password'])) return null;

        $base = rtrim($cred['api_base_url'], '/');

        try {
            $resp = Http::withoutVerifying()->timeout(15)->post("{$base}/auth/login", [
                'email'    => $cred['email'],
                'password' => $cred['password'],
            ]);

            if (!$resp->successful()) return null;

            $token = $resp->json('token');
            if ($token) Cache::put($cacheKey, $token, now()->addMinutes(20));

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
