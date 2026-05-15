<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🛡️ BUG-C4: Middleware de seguridad para el webhook de WhatsApp.
 *
 * Combina 3 capas:
 *   1. Token compartido (header X-Webhook-Token o query ?token=) opcional.
 *      Si se configura WHATSAPP_WEBHOOK_TOKEN en .env, es obligatorio.
 *   2. IP whitelist (config services.whatsapp.allowed_ips) opcional.
 *      Si está vacío, no aplica.
 *   3. Rate limiting por IP: 60 requests/minuto por defecto.
 *
 * Diseñado para NO romper la integración existente: si no hay token configurado,
 * solo aplica rate limiting. Permite migración gradual.
 */
class VerificarWebhookWhatsapp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // ── Capa 3: Rate limiting (siempre activo) ──
        $rateLimit = (int) config('services.whatsapp.rate_limit', 120); // 120 req/min por IP
        $cacheKey = 'whatsapp_webhook_rate:' . $ip;
        $hits = (int) Cache::get($cacheKey, 0);

        if ($hits >= $rateLimit) {
            Log::warning('🚫 Webhook rate limit excedido', [
                'ip'    => $ip,
                'hits'  => $hits,
                'limit' => $rateLimit,
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Rate limit exceeded',
            ], 429);
        }
        Cache::put($cacheKey, $hits + 1, now()->addMinute());

        // ── Capa 2: IP whitelist (opcional) ──
        $allowedIps = config('services.whatsapp.allowed_ips', []);
        if (!empty($allowedIps) && is_array($allowedIps)) {
            $isAllowed = false;
            foreach ($allowedIps as $allowed) {
                // Soportar IP exacta o CIDR simple (192.168.0.0/16)
                if ($this->matchIp($ip, trim($allowed))) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                Log::warning('🚫 Webhook IP no autorizada', [
                    'ip'      => $ip,
                    'allowed' => $allowedIps,
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'IP not allowed',
                ], 403);
            }
        }

        // ── Capa 1: Token compartido (opcional pero recomendado) ──
        $expectedToken = config('services.whatsapp.webhook_token');
        if (!empty($expectedToken)) {
            $providedToken = $request->header('X-Webhook-Token')
                ?? $request->query('token')
                ?? '';

            if (!hash_equals((string) $expectedToken, (string) $providedToken)) {
                Log::warning('🚫 Webhook token inválido', [
                    'ip'              => $ip,
                    'token_provisto'  => mb_substr((string) $providedToken, 0, 8) . '...',
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid webhook token',
                ], 401);
            }
        }

        return $next($request);
    }

    /**
     * Match IP exacta o CIDR (/8, /16, /24, /32).
     */
    private function matchIp(string $ip, string $pattern): bool
    {
        if (strpos($pattern, '/') === false) {
            return $ip === $pattern;
        }

        [$subnet, $bits] = explode('/', $pattern, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $bits = (int) $bits;

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
