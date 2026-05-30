<?php

namespace App\Services;

use App\Models\BotLeccion;
use App\Models\BotSugerencia;
use App\Models\ConfiguracionBot;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Ai\AiClientService;
use Illuminate\Support\Facades\Log;

/**
 * 💡 MODO SHADOW (copiloto) — entrena el bot SIN responderle al cliente.
 *
 * ⚠️ GARANTÍA DE SEGURIDAD: este servicio NUNCA envía mensajes. Solo:
 *   1. Genera una respuesta SUGERIDA (texto) con el cerebro del bot.
 *   2. La guarda en bot_sugerencias.
 *   3. El operador la ve en el chat y decide usar/editar/ignorar.
 *
 * No importa NINGÚN sender (WhatsApp/Meta). Es imposible que envíe al cliente.
 */
class BotShadowService
{
    public function __construct(private AiClientService $ai) {}

    /**
     * Genera (o devuelve la pendiente existente) la sugerencia para el último
     * mensaje del cliente en una conversación. Devuelve la BotSugerencia o null.
     */
    public function sugerirParaConversacion(ConversacionWhatsapp $conv): ?BotSugerencia
    {
        // Último mensaje del cliente
        $ultimoCliente = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->where('rol', 'user')
            ->orderByDesc('id')
            ->first();

        if (!$ultimoCliente) return null;

        // ¿El cliente es el último que habló? Si el operador ya respondió, no sugerimos.
        $ultimoMsg = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->orderByDesc('id')
            ->first();
        if (!$ultimoMsg || $ultimoMsg->rol !== 'user') {
            return null; // ya respondió un humano/bot, no hay nada que sugerir
        }

        // ¿Ya existe sugerencia pendiente para este mensaje? reutilizar
        $existente = BotSugerencia::query()
            ->where('conversacion_id', $conv->id)
            ->where('mensaje_cliente_id', $ultimoCliente->id)
            ->first();
        if ($existente) return $existente;

        // Generar nueva
        $texto = $this->generarTexto($conv);
        if (trim($texto) === '') return null;

        return BotSugerencia::create([
            'tenant_id'          => $conv->tenant_id,
            'conversacion_id'    => $conv->id,
            'mensaje_cliente_id' => $ultimoCliente->id,
            'sugerencia'         => $texto,
            'estado'             => BotSugerencia::ESTADO_PENDIENTE,
        ]);
    }

    /** Llama al LLM con el cerebro del bot — SOLO texto, sin tools, sin envío. */
    private function generarTexto(ConversacionWhatsapp $conv): string
    {
        try {
            $cfg = ConfiguracionBot::actual();
            $nombreBot = $cfg->nombre_asesora ?: 'la asesora';
            $empresa   = $cfg->empresa_descripcion ?: 'el negocio';

            // Lecciones aprendidas (las 81) — el conocimiento destilado
            $lecciones = BotLeccion::bloquePrompt($conv->tenant_id, 100);

            $system = <<<SYS
Sos {$nombreBot}, la asesora de ventas por WhatsApp de este negocio.

SOBRE EL NEGOCIO:
{$empresa}

Tu trabajo: responder al cliente como lo haría el mejor operador humano del
equipo — cálido, breve, colombiano, resolutivo. Mensajes cortos estilo
WhatsApp.

⚠️ REGLAS:
- NO inventes precios ni productos. Si no sabés un precio exacto, decí que
  lo confirmás enseguida (como hace el operador: "apenas facturen te paso el
  valor"). NUNCA inventes un número.
- Seguí las lecciones de abajo al pie de la letra.
- Respondé SOLO el mensaje que le mandarías al cliente, sin explicaciones.

{$lecciones}
SYS;

            // Historial reciente de la conversación (últimos 16 mensajes)
            $historial = MensajeWhatsapp::query()
                ->where('conversacion_id', $conv->id)
                ->orderByDesc('id')
                ->limit(16)
                ->get()
                ->reverse();

            $messages = [['role' => 'system', 'content' => $system]];
            foreach ($historial as $m) {
                if ($m->rol === 'user') {
                    $cont = trim((string) $m->contenido);
                    if ($cont === '') $cont = '[multimedia]';
                    $messages[] = ['role' => 'user', 'content' => $cont];
                } elseif ($m->rol === 'assistant') {
                    $cont = trim((string) $m->contenido);
                    if ($cont !== '') $messages[] = ['role' => 'assistant', 'content' => $cont];
                }
            }

            $resp = $this->ai->chat(
                messages: $messages,
                toolChoice: 'none',   // 🔒 sin tools: no ejecuta acciones, solo texto
                tools: null,
                opts: ['provider' => 'anthropic', 'temperature' => 0.4, 'max_tokens' => 600],
            );

            return trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Bot shadow: fallo generando sugerencia', [
                'conv' => $conv->id, 'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Registra la decisión del operador sobre una sugerencia.
     *
     * @param string $accion 'usada'|'editada'|'ignorada'
     * @param string|null $respuestaOperador lo que realmente envió (para medir similitud)
     */
    public function registrarDecision(BotSugerencia $sug, string $accion, ?string $respuestaOperador = null): void
    {
        $sim = null;
        if ($accion === BotSugerencia::ESTADO_EDITADA && $respuestaOperador) {
            similar_text(
                mb_strtolower($sug->sugerencia),
                mb_strtolower($respuestaOperador),
                $pct
            );
            $sim = (int) round($pct);
        } elseif ($accion === BotSugerencia::ESTADO_USADA) {
            $sim = 100;
        } elseif ($accion === BotSugerencia::ESTADO_IGNORADA) {
            $sim = 0;
        }

        $sug->update([
            'estado'             => $accion,
            'respuesta_operador' => $respuestaOperador,
            'similitud'          => $sim,
            'decidido_at'        => now(),
        ]);
    }

    /**
     * Métricas de precisión del bot (para saber si está listo para soltar).
     */
    public function metricas(int $tenantId, int $dias = 30): array
    {
        $base = BotSugerencia::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($dias))
            ->whereIn('estado', ['usada', 'editada', 'ignorada']); // solo decididas

        $total    = (clone $base)->count();
        $usadas   = (clone $base)->where('estado', 'usada')->count();
        $editadas = (clone $base)->where('estado', 'editada')->count();
        $ignoradas= (clone $base)->where('estado', 'ignorada')->count();

        // "Acierto" = usada tal cual + editada con alta similitud (>70%)
        $editadasBuenas = (clone $base)->where('estado', 'editada')->where('similitud', '>=', 70)->count();
        $aciertos = $usadas + $editadasBuenas;

        $precision = $total > 0 ? round(($aciertos / $total) * 100, 1) : 0;
        $simPromedio = (clone $base)->whereNotNull('similitud')->avg('similitud');

        return [
            'total_decididas' => $total,
            'usadas'          => $usadas,
            'editadas'        => $editadas,
            'ignoradas'       => $ignoradas,
            'precision'       => $precision,           // % — la "nota" del bot
            'similitud_prom'  => round((float) $simPromedio, 1),
            'pendientes'      => BotSugerencia::where('tenant_id', $tenantId)->where('estado', 'pendiente')->count(),
            'listo_para_soltar' => $total >= 50 && $precision >= 85, // umbral sugerido
        ];
    }
}
