<?php

namespace App\Services;

use App\Models\ConfiguracionBot;
use App\Models\ConversacionWhatsapp;
use App\Models\Sede;
use App\Models\Tenant;

/**
 * 🔍 BOT PROMPT INSPECTOR
 *
 * Reconstruye el prompt completo que el WhatsappWebhookController envía
 * a OpenAI para una conversación dada. Útil para debug:
 *  - Comando artisan bot:mostrar-prompt
 *  - Modal "Estado del pedido" pestaña "Prompt LLM"
 *  - Cualquier herramienta que necesite ver qué ve el LLM
 *
 * Devuelve un array estructurado con cada sección por separado, para
 * que la vista pueda renderizarlas con tabs/colapsables.
 */
class BotPromptInspectorService
{
    /**
     * @return array{
     *   meta: array,
     *   bloques: array<int,array{titulo:string,contenido:string,subtitulo?:string}>,
     *   historial: array<int,array{role:string,content:string}>,
     *   stats: array{caracteres:int,tokens_aprox:int,mensajes:int}
     * }
     */
    public function inspeccionar(ConversacionWhatsapp $conv, int $ultimosMensajes = 10): array
    {
        // Set tenant context
        if ($conv->tenant_id) {
            $tenant = Tenant::find($conv->tenant_id);
            if ($tenant) {
                app(TenantManager::class)->set($tenant);
            }
        }

        $config = ConfiguracionBot::actual();

        $bloques = [];

        // 1. SYSTEM PROMPT principal
        $systemPrompt = $this->construirSystemPrompt($conv, $config);
        $bloques[] = [
            'titulo'     => '1. System Prompt principal',
            'subtitulo'  => 'BotPromptService::plantillaGenerica() o personalizado',
            'contenido'  => $systemPrompt,
        ];

        // 2. Resumen del estado estructurado
        try {
            $resumen = app(EstadoPedidoService::class)->resumenParaPrompt($conv);
            if ($resumen !== '') {
                $bloques[] = [
                    'titulo'    => '2. Estado estructurado del pedido',
                    'subtitulo' => 'EstadoPedidoService → la "verdad" del pedido en BD',
                    'contenido' => $resumen . "\n\n🚨 Esta es la VERDAD ESTRUCTURADA del pedido. Úsala como input para confirmar_pedido. Si dice 'DATOS COMPLETOS' debes invocar `confirmar_pedido` AHORA con estos datos. No vuelvas a pedir lo que ya está aquí.",
                ];
            }
        } catch (\Throwable $e) {
            $bloques[] = [
                'titulo'    => '2. Estado estructurado',
                'subtitulo' => 'Error',
                'contenido' => "(error: {$e->getMessage()})",
            ];
        }

        // 3. Orquestador del flujo
        try {
            $orch = app(FlujoPedidoOrchestrator::class);
            $estado = app(EstadoPedidoService::class)->obtener($conv);
            $paso = $estado->paso_actual;
            // Pasar las tools al orquestador para que su system message las describa
            try {
                $todasToolsDefRaw = app(\App\Http\Controllers\WhatsappWebhookController::class)->getToolsDefinicion();
            } catch (\Throwable $eToolDef) {
                $todasToolsDefRaw = null;
            }
            $msgOrch = $orch->systemMessageParaPaso($conv, $todasToolsDefRaw);

            $tc = $orch->toolChoice($paso);
            $tcDesc = is_string($tc) ? $tc : 'function:' . ($tc['function']['name'] ?? '?');

            $bloques[] = [
                'titulo'    => "3. Orquestador del flujo (paso = {$paso})",
                'subtitulo' => "tool_choice: {$tcDesc} | tools: " . count($orch->toolsPermitidas($paso)),
                'contenido' => $msgOrch['content'],
            ];
        } catch (\Throwable $e) {
            // ignorar
        }

        // 4. Tools disponibles (todas las definidas) + cuáles permite el paso actual
        $tools = [];
        try {
            $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
            $todasTools = $controller->getToolsDefinicion();

            $orch = app(FlujoPedidoOrchestrator::class);
            $estado = app(EstadoPedidoService::class)->obtener($conv);
            $permitidasEnPaso = $orch->toolsPermitidas($estado->paso_actual);

            foreach ($todasTools as $t) {
                $nombre = $t['function']['name'] ?? '?';
                $tools[] = [
                    'nombre'        => $nombre,
                    'descripcion'   => $t['function']['description'] ?? '',
                    'parametros'    => $t['function']['parameters'] ?? [],
                    'permitida_paso'=> in_array($nombre, $permitidasEnPaso, true),
                ];
            }
        } catch (\Throwable $e) {
            // ignorar
        }

        // 5. Historial
        $historial = $conv->historialParaIA($ultimosMensajes);

        // Stats
        $totalChars = mb_strlen(implode("\n", array_column($bloques, 'contenido')))
            + mb_strlen(implode("\n", array_map(fn ($m) => $m['content'] ?? '', $historial)));
        $tokensAprox = (int) ceil($totalChars / 4);

        return [
            'meta' => [
                'conversacion_id' => $conv->id,
                'cliente_nombre'  => $conv->cliente?->nombre ?: $conv->telefono_normalizado,
                'telefono'        => $conv->telefono_normalizado,
                'tenant_id'       => $conv->tenant_id,
                'modelo'          => $config->modelo_openai ?: 'gpt-4o-mini',
                'temperatura'     => (float) ($config->temperatura ?? 0.85),
                'max_tokens'      => (int) ($config->max_tokens ?? 700),
            ],
            'bloques'   => $bloques,
            'tools'     => $tools,
            'historial' => $historial,
            'stats'     => [
                'caracteres'   => $totalChars,
                'tokens_aprox' => $tokensAprox,
                'mensajes'     => count($historial),
                'tools_total'  => count($tools),
                'tools_paso'   => count(array_filter($tools, fn ($t) => $t['permitida_paso'])),
            ],
        ];
    }

    /**
     * Replica getSystemPrompt() del controller (que es private).
     */
    private function construirSystemPrompt(ConversacionWhatsapp $conv, ConfiguracionBot $config): string
    {
        $promptService = app(BotPromptService::class);

        $name = $conv->cliente?->nombre ?: 'Cliente';
        $sedeId = null;
        if ($conv->connection_id) {
            $sede = Sede::query()->where('whatsapp_connection_id', $conv->connection_id)->first();
            $sedeId = $sede?->id;
        }

        $contexto = $promptService->construirContexto(
            $name,
            $sedeId,
            '',
            '',
            ''
        );

        $base = ($config->usar_prompt_personalizado && !empty(trim($config->system_prompt ?? '')))
            ? $config->system_prompt
            : BotPromptService::plantillaGenerica();

        // 💰 Replicar lógica de prompt-caching de getSystemPrompt():
        // las vars volátiles se anulan en el cuerpo y van al footer detrás
        // del marcador <<<CACHE_BREAK>>>.
        $varsVolatiles = [
            'fecha_actual', 'hora_actual', 'saludo_hora',
            'sede_estado_actual',
            'memoria_cliente', 'memoria_conversacion',
            'historial_cliente',
        ];
        $contextoEstable = $contexto;
        foreach ($varsVolatiles as $k) {
            $contextoEstable[$k] = '';
        }

        $prompt = $promptService->renderizar($base, $contextoEstable);

        $extra = trim((string) ($config->instrucciones_extra ?? ''));
        if ($extra !== '') {
            $prompt .= "\n\n--- INSTRUCCIONES EXTRA ---\n" . $extra;
        }

        // Footer volátil (igual que el real)
        $prompt .= "\n\n<<<CACHE_BREAK>>>\n\n"
                 . "═══════════════════════════════════════════════════════════════════════════════\n"
                 . "# 📅 CONTEXTO ACTUAL DEL TURNO (volátil — cambia cada mensaje)\n\n"
                 . "Hoy es **" . ($contexto['fecha_actual'] ?? '') . "** ("
                 . ($contexto['hora_actual'] ?? '') . "). Saludo: "
                 . ($contexto['saludo_hora'] ?? '') . ".\n";

        if (trim((string)($contexto['sede_estado_actual'] ?? '')) !== '') {
            $prompt .= "\nEstado de la sede: **" . $contexto['sede_estado_actual'] . "**\n";
        }
        if (trim((string)($contexto['memoria_cliente'] ?? '')) !== '') {
            $prompt .= "\n# 🧠 MEMORIA DEL CLIENTE\n" . $contexto['memoria_cliente'] . "\n";
        }
        if (trim((string)($contexto['memoria_conversacion'] ?? '')) !== '') {
            $prompt .= "\n# 💬 MEMORIA DE LA CONVERSACIÓN\n" . $contexto['memoria_conversacion'] . "\n";
        }
        if (trim((string)($contexto['historial_cliente'] ?? '')) !== '') {
            $prompt .= "\n# 📋 HISTORIAL DE PEDIDOS PREVIOS\n" . $contexto['historial_cliente'] . "\n";
        }

        return $prompt;
    }
}
