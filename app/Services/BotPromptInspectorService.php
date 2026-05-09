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
            $msgOrch = $orch->systemMessageParaPaso($conv);

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

        // 4. Historial
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
            'historial' => $historial,
            'stats'     => [
                'caracteres'   => $totalChars,
                'tokens_aprox' => $tokensAprox,
                'mensajes'     => count($historial),
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

        $prompt = $promptService->renderizar($base, $contexto);

        $extra = trim((string) ($config->instrucciones_extra ?? ''));
        if ($extra !== '') {
            $prompt .= "\n\n--- INSTRUCCIONES EXTRA ---\n" . $extra;
        }

        return $prompt;
    }
}
