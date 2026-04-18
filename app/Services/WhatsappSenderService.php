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
    private const ENDPOINT_LOGIN = 'https://wa-api.tecnobyteapp.com:1422/auth/login';
    private const ENDPOINT_SEND  = 'https://wa-api.tecnobyteapp.com:1422/api/messages/send';
    private const CACHE_KEY      = 'whatsapp_api_token';

    public function enviarTexto(
        string $telefono,
        string $mensaje,
        ?int $connectionId = null,
        bool $persistirEnConversacion = true,
        array $metaExtra = []
    ): bool {
        $token = $this->obtenerToken();
        if (!$token) {
            Log::error('WA Sender: token no disponible');
            return false;
        }

        $payload = [
            'number' => $telefono,
            'body'   => $mensaje,
        ];
        if ($connectionId) {
            $payload['whatsappId']   = $connectionId;
            $payload['connectionId'] = $connectionId;
        }

        $ok = false;

        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post(self::ENDPOINT_SEND, $payload);

            if ($resp->successful()) {
                $ok = true;
            } elseif ($resp->status() === 401) {
                // Reintentar con token fresco
                Cache::forget(self::CACHE_KEY);
                $nuevo = $this->obtenerToken();
                if ($nuevo) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevo)
                        ->timeout(20)
                        ->post(self::ENDPOINT_SEND, $payload);
                    $ok = $retry->successful();
                }
            } else {
                Log::warning('WA Sender: envío falló', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                    'to'     => $telefono,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WA Sender: excepción: ' . $e->getMessage());
            return false;
        }

        // 💾 Si envió OK, persistir en la conversación del cliente
        if ($ok && $persistirEnConversacion) {
            $this->persistirEnConversacion($telefono, $mensaje, $connectionId, $metaExtra);
        }

        return $ok;
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

    private function obtenerToken(): ?string
    {
        return Cache::remember(self::CACHE_KEY, 1200, function () {
            try {
                $resp = Http::withoutVerifying()
                    ->timeout(15)
                    ->post(self::ENDPOINT_LOGIN, [
                        'email'    => env('WHATSAPP_API_EMAIL'),
                        'password' => env('WHATSAPP_API_PASSWORD'),
                    ]);
                return $resp->successful() ? $resp->json('token') : null;
            } catch (\Throwable $e) {
                Log::error('WA Sender: login falló: ' . $e->getMessage());
                return null;
            }
        });
    }
}
