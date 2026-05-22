<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio simple para enviar mensajes de WhatsApp sin pasar por el webhook
 * (útil para broadcasts, felicitaciones, notificaciones automáticas, etc).
 * Reutiliza el mismo token cacheado que usa el controller.
 *
 * IMPORTANTE: tras enviar, persiste el mensaje en la conversación del cliente
 * (si `$persistirEnConversacion = true`), así aparece en `/chat` y queda en el
 * historial aunque el mensaje NO vino del bot ni del webhook.
 */
class WhatsappSenderService
{
    private const ENDPOINT_LOGIN_PATH = '/auth/login';
    private const ENDPOINT_SEND_PATH  = '/api/messages/send';

    public function enviarTexto(
        string $telefono,
        string $mensaje,
        ?int $connectionId = null,
        bool $persistirEnConversacion = true,
        array $metaExtra = []
    ): bool {
        // 📱 Dual provider: si el tenant tiene Meta WhatsApp activo, mandamos por ahí.
        // Si no, sigue con la API legacy (TecnoByteApp) como hasta hoy.
        try {
            $metaCfg = app(\App\Services\Meta\MetaWhatsappCloudService::class)->resolverConfig();
            if ($metaCfg) {
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarTexto($telefono, $mensaje, $metaCfg->tenant_id);
                if ($ok && $persistirEnConversacion) {
                    $this->persistirEnConversacion($telefono, $mensaje, $connectionId, array_merge(
                        $metaExtra,
                        ['provider' => 'meta']
                    ));
                }
                return $ok;
            }
        } catch (\Throwable $e) {
            Log::warning('WA Sender: chequeo Meta falló, usando legacy: ' . $e->getMessage());
        }

        // Resolver credenciales del tenant actual (provider legacy)
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $cacheKey = $resolver->tokenCacheKey();

        $token = $this->obtenerToken($cred, $cacheKey);
        if (!$token) {
            Log::error('WA Sender: token no disponible para tenant', [
                'tenant_id' => app(\App\Services\TenantManager::class)->id(),
            ]);
            return false;
        }

        // TecnoByteApp usa 'whatsappId'. NO 'connectionId' (causa ERR_SENDING_WAPP_MSG).
        $payload = [
            'number' => $telefono,
            'body'   => $mensaje,
        ];
        if ($connectionId) {
            $payload['whatsappId'] = $connectionId;
        }

        $endpointSend = rtrim($cred['api_base_url'], '/') . self::ENDPOINT_SEND_PATH;
        $ok = false;

        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post($endpointSend, $payload);

            if ($resp->successful()) {
                $ok = true;
            } elseif ($resp->status() === 401) {
                Cache::forget($cacheKey);
                $nuevo = $this->obtenerToken($cred, $cacheKey);
                if ($nuevo) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevo)
                        ->timeout(20)
                        ->post($endpointSend, $payload);
                    $ok = $retry->successful();
                }
            } else {
                Log::warning('WA Sender: envío falló', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                    'to'     => $telefono,
                    'tenant_id' => app(\App\Services\TenantManager::class)->id(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WA Sender: excepción: ' . $e->getMessage());
            return false;
        }

        if ($ok && $persistirEnConversacion) {
            $this->persistirEnConversacion($telefono, $mensaje, $connectionId, $metaExtra);
        }

        return $ok;
    }

    /**
     * Envía una imagen por URL pública con caption opcional.
     * Dual provider: Meta primero, legacy (TecnoByteApp) como fallback.
     *
     * @param string $telefono     E.164 sin +
     * @param string $imagenUrl    URL pública accesible
     * @param string $caption      Texto opcional al pie
     * @param int|null $connectionId  ID de conexión legacy (no aplica si va por Meta)
     */
    public function enviarImagen(
        string $telefono,
        string $imagenUrl,
        string $caption = '',
        ?int $connectionId = null,
        bool $persistirEnConversacion = true,
        array $metaExtra = []
    ): bool {
        // 📱 Dual provider: Meta primero
        try {
            $metaCfg = app(\App\Services\Meta\MetaWhatsappCloudService::class)->resolverConfig();
            if ($metaCfg) {
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarImagen($telefono, $imagenUrl, $caption ?: null, $metaCfg->tenant_id);
                if ($ok && $persistirEnConversacion) {
                    $this->persistirEnConversacion(
                        $telefono,
                        $caption ?: '[imagen]',
                        $connectionId,
                        array_merge($metaExtra, ['provider' => 'meta', 'media_url' => $imagenUrl, 'tipo' => 'image'])
                    );
                }
                return $ok;
            }
        } catch (\Throwable $e) {
            Log::warning('WA Sender imagen: chequeo Meta falló, usando legacy: ' . $e->getMessage());
        }

        // Legacy: TecnoByteApp expone /api/messages/send-media o similar.
        // Si tu API legacy no soporta media, este path retorna false.
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $cacheKey = $resolver->tokenCacheKey();
        $token = $this->obtenerToken($cred, $cacheKey);
        if (!$token) return false;

        // 📎 TecnoByteApp / EstradaHubWhatsApp legacy: usa POST /api/messages/send
        // con multipart/form-data y el archivo bajo el campo "medias" (array).
        // NO acepta media_url como JSON — debe ser un attachment binario.
        try {
            // 1. Obtener bytes de la imagen (desde storage local si es nuestra URL, sino HTTP)
            $bytes    = null;
            $filename = 'image.jpg';
            $mime     = 'image/jpeg';

            $publicBase = config('app.url', '');
            if ($publicBase && str_starts_with($imagenUrl, $publicBase)) {
                // URL de nuestro propio storage → leer directamente del disco
                $relativo = ltrim(str_replace($publicBase, '', $imagenUrl), '/');
                if (str_starts_with($relativo, 'storage/')) {
                    $pathDisco = substr($relativo, strlen('storage/'));
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($pathDisco)) {
                        $bytes    = \Illuminate\Support\Facades\Storage::disk('public')->get($pathDisco);
                        $filename = basename($pathDisco);
                        $mime     = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($pathDisco) ?: 'image/jpeg';
                    }
                }
            }

            // Fallback: descargar la URL externa
            if ($bytes === null) {
                $download = Http::withoutVerifying()->timeout(20)->get($imagenUrl);
                if (!$download->successful()) {
                    Log::warning('WA Sender imagen: no se pudo descargar URL', [
                        'url' => $imagenUrl,
                        'status' => $download->status(),
                    ]);
                    return false;
                }
                $bytes = $download->body();
                $filename = basename(parse_url($imagenUrl, PHP_URL_PATH) ?: 'image.jpg');
                $mime = $download->header('Content-Type') ?: 'image/jpeg';
            }

            // 2. POST multipart al endpoint correcto con el archivo como "medias"
            $endpoint = rtrim($cred['api_base_url'], '/') . '/api/messages/send';
            $request  = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(45)
                ->attach('medias', $bytes, $filename, ['Content-Type' => $mime]);

            $payload = [
                'number' => $telefono,
                'body'   => $caption,
            ];
            if ($connectionId) $payload['whatsappId'] = $connectionId;

            $resp = $request->post($endpoint, $payload);

            $ok = $resp->successful();
            if (!$ok) {
                Log::warning('WA Sender imagen legacy: falló', [
                    'endpoint' => $endpoint,
                    'status'   => $resp->status(),
                    'body'     => mb_substr($resp->body(), 0, 300),
                ]);
            } else {
                Log::info('WA Sender imagen legacy: enviada ✓', [
                    'to' => $telefono,
                    'filename' => $filename,
                    'size' => strlen($bytes),
                ]);
            }

            if ($ok && $persistirEnConversacion) {
                $this->persistirEnConversacion(
                    $telefono,
                    $caption ?: '[imagen]',
                    $connectionId,
                    array_merge($metaExtra, ['provider' => 'legacy', 'media_url' => $imagenUrl, 'tipo' => 'image'])
                );
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::error('WA Sender imagen excepción: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            return false;
        }
    }

    /**
     * Guarda el mensaje enviado en la conversación del cliente.
     * Si no existe conversación activa, la crea. Así el mensaje aparece
     * en /chat como cualquier otro mensaje saliente.
     */
    private function persistirEnConversacion(
        string $telefono,
        string $mensaje,
        ?int $connectionId,
        array $metaExtra
    ): void {
        try {
            $telefonoNorm = $this->normalizarTelefono($telefono);
            if (!$telefonoNorm) return;

            $cliente = Cliente::encontrarOCrearPorTelefono($telefonoNorm);
            if (!$cliente) return;

            $convService = app(ConversacionService::class);
            $conversacion = $convService->obtenerOCrearActiva(
                $telefonoNorm,
                $cliente->id,
                null,
                $connectionId
            );

            $convService->agregarMensaje(
                $conversacion,
                MensajeWhatsapp::ROL_ASSISTANT,
                $mensaje,
                [
                    'meta' => array_merge([
                        'origen' => 'sistema_automatico',
                    ], $metaExtra),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('WA Sender: no se persistió en conversación: ' . $e->getMessage());
        }
    }

    /**
     * Normaliza el teléfono para comparación/almacenamiento.
     * Quita @c.us, espacios, guiones, etc. Deja solo dígitos.
     */
    private function normalizarTelefono(string $tel): string
    {
        $tel = preg_replace('/@c\.us|@s\.whatsapp\.net/i', '', $tel);
        $tel = preg_replace('/\D+/', '', $tel);
        return $tel;
    }

    private function obtenerToken(array $cred, string $cacheKey): ?string
    {
        if (empty($cred['email']) || empty($cred['password'])) {
            Log::warning('WA Sender: credenciales vacías', [
                'tenant_id' => app(\App\Services\TenantManager::class)->id(),
            ]);
            return null;
        }

        return Cache::remember($cacheKey, 1200, function () use ($cred) {
            try {
                $endpoint = rtrim($cred['api_base_url'], '/') . self::ENDPOINT_LOGIN_PATH;
                $resp = Http::withoutVerifying()
                    ->timeout(15)
                    ->post($endpoint, [
                        'email'    => $cred['email'],
                        'password' => $cred['password'],
                    ]);
                return $resp->successful() ? $resp->json('token') : null;
            } catch (\Throwable $e) {
                Log::error('WA Sender: login falló: ' . $e->getMessage());
                return null;
            }
        });
    }
}
