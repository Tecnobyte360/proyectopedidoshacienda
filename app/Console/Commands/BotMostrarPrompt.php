<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionBot;
use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Services\BotPromptService;
use App\Services\EstadoPedidoService;
use App\Services\FlujoPedidoOrchestrator;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * 🔍 BOT MOSTRAR PROMPT
 *
 * Reconstruye y muestra el prompt completo que se envía a OpenAI para
 * una conversación dada. Útil para debug: ver exactamente qué ve el LLM
 * en cada turno antes de responder.
 *
 * Uso:
 *   php artisan bot:mostrar-prompt 19              (conv id 19, salida en stdout)
 *   php artisan bot:mostrar-prompt 19 --file=p.txt (volcar a archivo)
 *   php artisan bot:mostrar-prompt 19 --con-reglas (incluir reglas del orquestador)
 */
class BotMostrarPrompt extends Command
{
    protected $signature = 'bot:mostrar-prompt
                            {conversacion : ID de la conversación}
                            {--file= : Volcar a archivo en vez de stdout}
                            {--ultimos=20 : Cantidad de mensajes del historial a incluir}';

    protected $description = 'Reconstruye y muestra el prompt completo enviado a OpenAI para una conversación';

    public function handle(): int
    {
        $convId = (int) $this->argument('conversacion');
        $conv = ConversacionWhatsapp::with('cliente')->find($convId);

        if (!$conv) {
            $this->error("Conversación #{$convId} no encontrada.");
            return self::FAILURE;
        }

        // Set tenant context
        if ($conv->tenant_id) {
            app(TenantManager::class)->setIdManual($conv->tenant_id);
        }

        $config = ConfiguracionBot::actual();

        $output = [];
        $output[] = str_repeat('═', 80);
        $output[] = "🔍 PROMPT QUE SE ENVÍA A OPENAI";
        $output[] = "Conversación: #{$conv->id}  |  Cliente: " . ($conv->cliente?->nombre ?: $conv->telefono_normalizado);
        $output[] = "Tenant: {$conv->tenant_id}  |  Modelo: " . ($config->modelo_openai ?: 'gpt-4o-mini');
        $output[] = str_repeat('═', 80);
        $output[] = '';

        // ═══ 1. SYSTEM PROMPT PRINCIPAL ═══
        $output[] = $this->bloque('1. SYSTEM PROMPT PRINCIPAL (BotPromptService)');
        $systemPrompt = $this->construirSystemPrompt($conv, $config);
        $output[] = $systemPrompt;
        $output[] = '';

        // ═══ 2. RESUMEN DE ESTADO DEL PEDIDO ═══
        $resumenEstado = '';
        try {
            $resumenEstado = app(EstadoPedidoService::class)->resumenParaPrompt($conv);
        } catch (\Throwable $e) {
            $resumenEstado = "(error obteniendo resumen: {$e->getMessage()})";
        }

        if ($resumenEstado !== '') {
            $output[] = $this->bloque('2. SYSTEM — RESUMEN DEL ESTADO ESTRUCTURADO (EstadoPedidoService)');
            $output[] = $resumenEstado;
            $output[] = '';
            $output[] = "🚨 [Inyectado siempre que hay estado activo. Es la 'verdad' del pedido.]";
            $output[] = '';
        }

        // ═══ 3. ORQUESTADOR DEL FLUJO ═══
        try {
            $orch = app(FlujoPedidoOrchestrator::class);
            $estado = app(EstadoPedidoService::class)->obtener($conv);
            $paso = $estado->paso_actual;
            $msgOrch = $orch->systemMessageParaPaso($conv);

            $output[] = $this->bloque("3. SYSTEM — ORQUESTADOR DE FLUJO (paso = {$paso})");
            $output[] = $msgOrch['content'];
            $output[] = '';

            $output[] = "→ tool_choice: " . (is_string($orch->toolChoice($paso))
                ? $orch->toolChoice($paso)
                : 'function:' . ($orch->toolChoice($paso)['function']['name'] ?? '?'));
            $output[] = "→ tools permitidas: " . implode(', ', $orch->toolsPermitidas($paso));
            $output[] = '';
        } catch (\Throwable $e) {
            $output[] = "(error al obtener orquestador: {$e->getMessage()})";
        }

        // ═══ 4. HISTORIAL DE MENSAJES ═══
        $output[] = $this->bloque("4. HISTORIAL — últimos {$this->option('ultimos')} mensajes user/assistant");
        $historial = $conv->historialParaIA((int) $this->option('ultimos'));
        if (empty($historial)) {
            $output[] = '(sin mensajes en el historial — ¿aislamiento por día activo y conversación de ayer?)';
        } else {
            foreach ($historial as $i => $m) {
                $rol = strtoupper($m['role']);
                $contenido = mb_substr((string) $m['content'], 0, 500);
                $output[] = "[{$i}] [{$rol}] {$contenido}";
            }
        }
        $output[] = '';

        // ═══ 5. RESUMEN DE TOTALES ═══
        $output[] = $this->bloque('5. RESUMEN');
        $totalChars = mb_strlen(implode("\n", $output));
        $tokensAprox = (int) ceil($totalChars / 4);
        $output[] = "Caracteres totales: " . number_format($totalChars);
        $output[] = "Tokens aproximados: ~" . number_format($tokensAprox);
        $output[] = "Mensajes en historial: " . count($historial);
        $output[] = '';
        $output[] = str_repeat('═', 80);

        $contenidoFinal = implode("\n", $output);

        if ($this->option('file')) {
            file_put_contents($this->option('file'), $contenidoFinal);
            $this->info("✅ Prompt volcado a: " . $this->option('file'));
        } else {
            $this->line($contenidoFinal);
        }

        return self::SUCCESS;
    }

    private function bloque(string $titulo): string
    {
        return "\n" . str_repeat('─', 80) . "\n║ {$titulo}\n" . str_repeat('─', 80);
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
            $sede = \App\Models\Sede::query()->where('whatsapp_connection_id', $conv->connection_id)->first();
            $sedeId = $sede?->id;
        }

        $contexto = $promptService->construirContexto(
            $name,
            $sedeId,
            '', // infoEmpresa
            '', // pedidosInfo
            ''  // ansInfo
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
