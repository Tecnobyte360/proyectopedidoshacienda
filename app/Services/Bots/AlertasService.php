<?php

namespace App\Services\Bots;

use App\Models\BotAlerta;
use App\Models\ConversacionWhatsapp;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 🚨 SERVICIO DE ALERTAS PROACTIVAS
 *
 * Detecta condiciones críticas y envía notificaciones por:
 *   - Email al admin (si está configurado)
 *   - Webhook Telegram/Slack/etc (si está configurado)
 *   - Cache: marca cooldown para no enviar duplicados
 *
 * Alertas que dispara:
 *   • 3+ errores consecutivos del proveedor IA en 5 min
 *   • Conversación con > 8 mensajes sin cerrar pedido
 *   • Cliente expresó frustración / pidió humano
 *   • Pedido falló al exportar a SGI
 *   • API key con problemas (401, saldo agotado)
 *
 * Cooldown: cada tipo de alerta se manda max 1 vez cada 30 min.
 */
class AlertasService
{
    private const COOLDOWN_MIN = 30;

    /**
     * Notificación crítica genérica con cooldown.
     */
    public function notificar(string $tipo, string $titulo, string $mensaje, array $contexto = []): void
    {
        $tenantId = optional(app(\App\Services\TenantManager::class)->current())->id ?? 'global';
        $cooldownKey = "alerta_cooldown_t{$tenantId}_{$tipo}";

        // Si ya enviamos esta alerta hace <30 min, no repetir
        if (Cache::has($cooldownKey)) {
            Log::info('🔕 Alerta en cooldown — no se reenvía', ['tipo' => $tipo, 'tenant' => $tenantId]);
            return;
        }

        // Persistir en bot_alertas
        try {
            BotAlerta::create([
                'tenant_id' => is_int($tenantId) ? $tenantId : null,
                'tipo'      => $tipo,
                'titulo'    => $titulo,
                'mensaje'   => $mensaje,
                'severidad' => $contexto['severidad'] ?? BotAlerta::SEV_ALTA,
                'contexto'  => $contexto,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo persistir BotAlerta: ' . $e->getMessage());
        }

        // Enviar email si configurado
        $this->enviarEmail($titulo, $mensaje, $contexto);

        // Enviar Telegram/webhook si configurado
        $this->enviarWebhook($titulo, $mensaje, $contexto);

        Cache::put($cooldownKey, true, now()->addMinutes(self::COOLDOWN_MIN));

        Log::info('🚨 Alerta enviada', [
            'tipo'    => $tipo,
            'titulo'  => $titulo,
            'tenant'  => $tenantId,
        ]);
    }

    private function enviarEmail(string $titulo, string $mensaje, array $contexto): void
    {
        $tenant = app(\App\Services\TenantManager::class)->current();
        $emailAdmin = trim((string) (
            $tenant?->email
            ?? env('ADMIN_ALERT_EMAIL')
            ?? config('mail.from.address')
        ));
        if ($emailAdmin === '') return;

        try {
            Mail::raw(
                $mensaje . "\n\n" . "Contexto:\n" . json_encode($contexto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                function ($m) use ($emailAdmin, $titulo, $tenant) {
                    $tnombre = $tenant?->nombre ?? 'Bot';
                    $m->to($emailAdmin)
                        ->subject("🚨 [{$tnombre}] {$titulo}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Fallo envío email alerta: ' . $e->getMessage());
        }
    }

    /**
     * Envía la alerta a un webhook genérico (Telegram, Slack, Discord, etc.)
     * configurado en .env como ALERT_WEBHOOK_URL.
     */
    private function enviarWebhook(string $titulo, string $mensaje, array $contexto): void
    {
        $url = trim((string) env('ALERT_WEBHOOK_URL', ''));
        if ($url === '') return;

        try {
            $payload = [
                'text'    => "*{$titulo}*\n{$mensaje}",
                'titulo'  => $titulo,
                'mensaje' => $mensaje,
                'contexto'=> $contexto,
                'tenant'  => optional(app(\App\Services\TenantManager::class)->current())->nombre,
            ];
            \Illuminate\Support\Facades\Http::timeout(5)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Fallo webhook alerta: ' . $e->getMessage());
        }
    }

    /**
     * Detecta si hubo N errores del proveedor IA en los últimos M minutos
     * y dispara alerta si supera el umbral.
     */
    public function chequearErroresIaConsecutivos(): void
    {
        $errores = BotAlerta::where('tipo', BotAlerta::TIPO_OPENAI_TIMEOUT)
            ->orWhere('tipo', 'openai_error')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($errores >= 3) {
            $this->notificar(
                'ia_caida',
                '⚠️ Proveedor IA con problemas',
                "Se detectaron {$errores} errores del proveedor IA en los últimos 5 minutos. Posibles causas: API key vencida, saldo agotado, caída del servicio. El bot puede estar respondiendo con errores a los clientes.",
                ['errores_5min' => $errores, 'severidad' => BotAlerta::SEV_CRITICA]
            );
        }
    }
}
