<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio simple para enviar mensajes de WhatsApp sin pasar por el webhook
 * (útil para broadcasts, felicitaciones, notificaciones automáticas, etc).
 * Reutiliza el mismo token cacheado que usa el controller.
 */
class WhatsappSenderService
{
    private const ENDPOINT_LOGIN = 'https://wa-api.tecnobyteapp.com:1422/auth/login';
    private const ENDPOINT_SEND  = 'https://wa-api.tecnobyteapp.com:1422/api/messages/send';
    private const CACHE_KEY      = 'whatsapp_api_token';

    public function enviarTexto(string $telefono, string $mensaje, ?int $connectionId = null): bool
    {
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

        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post(self::ENDPOINT_SEND, $payload);

            if ($resp->successful()) return true;

            // Reintentar con token fresco
            if ($resp->status() === 401) {
                Cache::forget(self::CACHE_KEY);
                $nuevo = $this->obtenerToken();
                if ($nuevo) {
                    $retry = Http::withoutVerifying()
                        ->withToken($nuevo)
                        ->timeout(20)
                        ->post(self::ENDPOINT_SEND, $payload);
                    return $retry->successful();
                }
            }

            Log::warning('WA Sender: envío falló', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
                'to'     => $telefono,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WA Sender: excepción: ' . $e->getMessage());
            return false;
        }
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
