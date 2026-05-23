<?php

namespace App\Services\Bots;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\Log;

/**
 * 🚑 CONVERSATION RESCUE AGENT
 *
 * Detecta conversaciones donde el bot quedó atorado respondiendo errores
 * repetidos (ERP caído, API timeout, prompts mal calculados) y rescata
 * al cliente activando MODO HUMANO + notificando al operador.
 *
 * Patrón típico que detectamos (caso real del log 22:46 → 22:58):
 *   bot: "⚠️ Tuve un problema al registrarte..."
 *   cliente: "?"
 *   bot: "⚠️ Tuve un problema al registrarte..."  ← repetido
 *   cliente: (silencio o más quejas)
 *
 * Acción:
 *   1. Marca `atendida_por_humano = true` → bot deja de responder
 *   2. Dispara alerta crítica via AlertasService (email + webhook + BotAlerta)
 *   3. Log detallado para auditoría
 *
 * IDEMPOTENCIA: si ya está en modo humano, no hace nada. Si ya alertamos
 * por esta conversación recientemente, no reenvía (cooldown del AlertasService).
 */
class ConversationRescueAgent
{
    /**
     * Keywords típicos de mensajes "bot atorado en error". Si los últimos
     * mensajes del bot contienen N de estos patrones, es bot atorado.
     */
    private const PATRONES_ERROR_BOT = [
        'tuve un problema',
        'intenta de nuevo',
        'intenta luego',
        'intenta más tarde',
        'llama a la sede',
        'no se pudo',
        'error técnico',
        'error al procesar',
        'algo salió mal',
        'no pude registrar',
        'no logré',
    ];

    /**
     * Mínimo de mensajes-error del bot para activar rescate.
     */
    private const MIN_ERRORES_BOT = 2;

    /**
     * Ventana de análisis (últimos N minutos).
     */
    private const VENTANA_MIN = 30;

    /**
     * Mínimo de minutos desde el último error antes de rescatar
     * (le damos al ErpRetryQueueAgent una oportunidad de resolverlo solo).
     */
    private const ESPERAR_MIN_ANTES_RESCATAR = 3;

    /**
     * Ejecuta una pasada de detección + rescate.
     *
     * @return array{revisadas:int, rescatadas:int, ya_en_handoff:int}
     */
    public function detectarYRescatar(): array
    {
        $stats = ['revisadas' => 0, 'rescatadas' => 0, 'ya_en_handoff' => 0];

        // Conversaciones con actividad reciente del bot Y del cliente
        $convs = ConversacionWhatsapp::withoutGlobalScopes()
            ->where('updated_at', '>=', now()->subMinutes(self::VENTANA_MIN))
            ->get();

        foreach ($convs as $conv) {
            $stats['revisadas']++;

            // Saltar si ya está en modo humano
            if ($conv->atendida_por_humano) {
                $stats['ya_en_handoff']++;
                continue;
            }

            if (!$this->estaAtorada($conv)) continue;

            $this->rescatar($conv);
            $stats['rescatadas']++;
        }

        if ($stats['rescatadas'] > 0) {
            Log::warning('🚑 ConversationRescueAgent: pasada completada', $stats);
        }

        return $stats;
    }

    /**
     * ¿La conversación está atorada en error?
     *
     * Criterios (deben cumplirse TODOS):
     *   1. Últimos N mensajes del bot incluyen ≥2 con patrones de error
     *   2. El cliente escribió ≥1 mensaje DESPUÉS del primer error del bot
     *      (indica que sigue esperando respuesta — no se fue)
     *   3. Han pasado ≥3 min desde el último mensaje del bot
     *      (margen para que ErpRetryQueueAgent se resuelva solo)
     */
    private function estaAtorada(ConversacionWhatsapp $conv): bool
    {
        $msgs = MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->whereIn('rol', [MensajeWhatsapp::ROL_USER, MensajeWhatsapp::ROL_ASSISTANT])
            ->where('created_at', '>=', now()->subMinutes(self::VENTANA_MIN))
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        if ($msgs->isEmpty()) return false;

        // 1) ¿Bot envió ≥N mensajes con patrones de error?
        $erroresBot = $msgs->filter(fn ($m) =>
            $m->rol === MensajeWhatsapp::ROL_ASSISTANT
            && $this->contieneError((string) $m->contenido)
        );

        if ($erroresBot->count() < self::MIN_ERRORES_BOT) return false;

        // 2) ¿Cliente respondió DESPUÉS del primer error del bot?
        $primerErrorId = $erroresBot->first()->id;
        $clienteRespondioDespues = $msgs
            ->filter(fn ($m) => $m->rol === MensajeWhatsapp::ROL_USER && $m->id > $primerErrorId)
            ->isNotEmpty();

        if (!$clienteRespondioDespues) return false;

        // 3) ¿Pasaron ≥3 min desde el último mensaje del bot?
        // ⚠️ Carbon 3 devuelve negativo en diffInMinutes de fecha pasada — abs()
        $ultimoMsgBot = $msgs->where('rol', MensajeWhatsapp::ROL_ASSISTANT)->last();
        if ($ultimoMsgBot) {
            $minutosDesdeUltimo = abs(\Carbon\Carbon::parse($ultimoMsgBot->created_at)->diffInMinutes(now()));
            if ($minutosDesdeUltimo < self::ESPERAR_MIN_ANTES_RESCATAR) {
                return false;
            }
        }

        return true;
    }

    private function contieneError(string $contenido): bool
    {
        $norm = mb_strtolower($contenido);
        foreach (self::PATRONES_ERROR_BOT as $p) {
            if (str_contains($norm, $p)) return true;
        }
        return false;
    }

    private function rescatar(ConversacionWhatsapp $conv): void
    {
        // 1. Marcar handoff humano — el bot deja de responder
        $conv->update([
            'atendida_por_humano' => true,
        ]);

        // 2. Disparar alerta para el operador
        try {
            $tenant = $conv->tenant_id ? \App\Models\Tenant::find($conv->tenant_id) : null;
            if ($tenant) {
                app(\App\Services\TenantManager::class)->set($tenant);
            }

            $telefono = $conv->telefono_normalizado;
            $cliente  = $conv->cliente?->nombre ?: 'sin nombre';
            $linkChat = url('/chat?conv=' . $conv->id);

            $titulo = "🚑 Conversación rescatada — bot atorado";
            $mensaje = "El bot quedó atorado respondiendo errores a este cliente:\n\n"
                . "• Cliente: {$cliente} ({$telefono})\n"
                . "• Conversación #{$conv->id}\n"
                . "• Modo HUMANO activado — el bot YA no responde\n\n"
                . "👉 Un operador debe responderle ahora desde:\n{$linkChat}";

            app(AlertasService::class)->notificar(
                tipo:    "rescate_conv_{$conv->id}",
                titulo:  $titulo,
                mensaje: $mensaje,
                contexto: [
                    'conversacion_id' => $conv->id,
                    'tenant_id'       => $conv->tenant_id,
                    'telefono'        => $telefono,
                    'cliente'         => $cliente,
                    'severidad'       => \App\Models\BotAlerta::SEV_CRITICA,
                    'link'            => $linkChat,
                ]
            );

            Log::warning('🚑 ConversationRescueAgent: conversación rescatada', [
                'conv_id'  => $conv->id,
                'telefono' => $telefono,
                'cliente'  => $cliente,
            ]);

        } catch (\Throwable $e) {
            Log::error('💥 ConversationRescueAgent: fallo al alertar - ' . $e->getMessage());
        }
    }
}
