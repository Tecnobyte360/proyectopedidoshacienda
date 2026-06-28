<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de notificaciones push vía Firebase Cloud Messaging (HTTP v1).
 *
 * No requiere paquetes extra: genera el JWT del service account con openssl,
 * lo intercambia por un access_token OAuth2 (cacheado 55 min) y publica en
 * fcm/v1/projects/{project_id}/messages:send.
 *
 * Credencial: archivo JSON del service account en storage/app/firebase/service-account.json
 * (Firebase → Configuración del proyecto → Cuentas de servicio → Generar nueva clave privada).
 */
class FcmService
{
    private function credenciales(): ?array
    {
        $path = storage_path('app/firebase/service-account.json');
        if (!is_file($path)) {
            Log::warning('FCM: falta service-account.json en storage/app/firebase/');
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function accessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            $sa = $this->credenciales();
            if (!$sa) return null;

            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim  = $this->b64(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));
            $signatureInput = $header . '.' . $claim;
            $signature = '';
            openssl_sign($signatureInput, $signature, $sa['private_key'], 'sha256WithRSAEncryption');
            $jwt = $signatureInput . '.' . $this->b64($signature);

            $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);
            if (!$resp->ok()) {
                Log::error('FCM: no se pudo obtener access_token: ' . $resp->body());
                return null;
            }
            return $resp->json('access_token');
        });
    }

    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** Envía una notificación a un token concreto. */
    public function enviarAToken(string $token, string $titulo, string $cuerpo, array $data = []): bool
    {
        $sa = $this->credenciales();
        $access = $this->accessToken();
        if (!$sa || !$access) return false;

        $resp = Http::withToken($access)->post(
            "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send",
            ['message' => [
                'token'        => $token,
                'notification' => ['title' => $titulo, 'body' => $cuerpo],
                'data'         => array_map(fn ($v) => (string) $v, $data),
                'android'      => ['priority' => 'high', 'notification' => ['sound' => 'default']],
            ]]
        );

        if ($resp->status() === 404 || $resp->status() === 400) {
            // Token inválido/expirado → limpiar
            DeviceToken::where('token', $token)->delete();
        }
        if (!$resp->ok()) {
            Log::warning('FCM enviar: ' . $resp->status() . ' ' . $resp->body());
            return false;
        }
        return true;
    }

    /** Envía a una colección de tokens (devuelve cuántos se entregaron). */
    public function enviarAMuchos($tokens, string $titulo, string $cuerpo, array $data = []): int
    {
        $ok = 0;
        foreach (array_unique((array) $tokens) as $t) {
            if ($t && $this->enviarAToken($t, $titulo, $cuerpo, $data)) $ok++;
        }
        return $ok;
    }
}
