<?php

namespace App\Services;

use App\Models\ChatWidget;
use App\Models\ChatWidgetMensaje;
use App\Models\ChatWidgetSesion;
use App\Models\ConfiguracionBot;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WidgetChatService
{
    public function __construct(private BotPromptService $promptService) {}

    /**
     * Genera una respuesta con OpenAI usando el mismo prompt base del bot WhatsApp.
     * Historial: últimos 20 mensajes de la sesión del widget.
     */
    public function responder(ChatWidget $widget, ChatWidgetSesion $sesion, string $mensaje): string
    {
        $tenant = $widget->tenant ?: Tenant::find($widget->tenant_id);
        app(TenantManager::class)->set($tenant);

        $config = ConfiguracionBot::actual();

        // Historial
        $historial = ChatWidgetMensaje::where('sesion_id', $sesion->id)
            ->whereIn('rol', [ChatWidgetMensaje::ROL_USER, ChatWidgetMensaje::ROL_ASSISTANT])
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->rol, 'content' => $m->contenido])
            ->values()
            ->all();

        // Construir contexto (catálogo, promos, zonas, etc.)
        $nombreVisitante = $sesion->visitante_nombre ?: 'Visitante';
        $contexto = $this->promptService->construirContexto(
            $nombreVisitante,
            null,                      // sin sede específica
            (string) $tenant?->nombre,
            '',                        // sin info de pedidos previos en widget
            ''                         // sin ans info
        );

        $basePrompt = ($config->usar_prompt_personalizado && !empty(trim($config->system_prompt ?? '')))
            ? $config->system_prompt
            : BotPromptService::plantillaPorDefecto();

        $systemPrompt = $this->promptService->renderizar($basePrompt, $contexto);

        // Aviso especial para contexto widget web (no WhatsApp)
        $systemPrompt .= "\n\n═══════════════════════════════════════════════════════════════════════════════\n"
            . "# 🌐 CANAL: WIDGET WEB\n\n"
            . "Estás atendiendo a un visitante desde el sitio web del negocio, NO por WhatsApp.\n"
            . "- El visitante puede no tener cuenta. NO asumas historial de pedidos previos.\n"
            . "- No llames a `confirmar_pedido`: el widget solo conversa y orienta, no registra pedidos.\n"
            . "- Si el visitante muestra intención de comprar en firme, sugiere amablemente que continúe por WhatsApp al número del negocio para cerrar el pedido.\n"
            . "- Mantén tu tono natural y servicial.\n";

        // Extra instrucciones configurables
        $extra = trim((string) ($config->instrucciones_extra ?? ''));
        if ($extra !== '') {
            $systemPrompt .= "\n\n# 🔧 REGLAS ADICIONALES\n\n" . $this->promptService->renderizar($extra, $contexto);
        }

        // Llamar a OpenAI
        $apiKey = Tenant::resolverOpenaiKey() ?? env('OPENAI_API_KEY');
        if (!$apiKey) {
            return 'El servicio de mensajería está temporalmente no disponible. Por favor inténtalo más tarde.';
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $historial,
            [['role' => 'user', 'content' => $mensaje]],
        );

        try {
            $resp = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => $config->modelo_openai ?: 'gpt-4o-mini',
                    'temperature' => (float) ($config->temperatura ?? 0.7),
                    'max_tokens'  => (int) ($config->max_tokens ?? 700),
                    'messages'    => $messages,
                ]);

            if (!$resp->successful()) {
                Log::warning('Widget OpenAI no-200: ' . $resp->body());
                return 'Tuve un problemita procesando tu mensaje. ¿Podrías repetirlo?';
            }

            $texto = trim((string) ($resp->json('choices.0.message.content') ?? ''));
            return $texto !== '' ? $texto : '¿Me das más detalles de lo que buscas?';
        } catch (\Throwable $e) {
            Log::error('Widget OpenAI excepción: ' . $e->getMessage());
            return 'Uy, me quedé sin poder responderte. Intenta en un momento.';
        }
    }
}
