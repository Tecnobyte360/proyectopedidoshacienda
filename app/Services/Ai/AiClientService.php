<?php

namespace App\Services\Ai;

use App\Models\BotAlerta;
use App\Models\ConfiguracionBot;
use App\Models\Tenant;
use App\Services\BotAlertaService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🤖 ROUTER DE PROVEEDORES IA — OpenAI o Anthropic.
 *
 * Lee la configuración del bot del tenant actual:
 *   - ai_provider: 'openai' | 'anthropic'
 *   - modelo_openai (si openai) o modelo_anthropic (si anthropic)
 *   - api key del proveedor seleccionado
 *
 * Devuelve la respuesta SIEMPRE en formato OpenAI para que el resto del
 * código no necesite cambios. Incluye reintentos con backoff y registro
 * de alertas críticas.
 */
class AiClientService
{
    private const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
    private const INTENTOS = 4;

    /**
     * Modelos OpenAI soportados.
     */
    public const MODELOS_OPENAI = [
        'gpt-4o-mini'          => 'GPT-4o mini (rápido, barato)',
        'gpt-4o'               => 'GPT-4o (más capaz)',
        'gpt-4-turbo'          => 'GPT-4 Turbo',
        'gpt-3.5-turbo'        => 'GPT-3.5 Turbo (legacy)',
    ];

    /**
     * Llama al proveedor de IA configurado para el tenant actual.
     * Recibe los mismos parámetros que la API de OpenAI.
     *
     * @return array|null Respuesta normalizada en formato OpenAI o null si falla
     */
    public function chat(array $messages, $toolChoice = 'auto', ?array $tools = null, array $opts = []): ?array
    {
        $config = ConfiguracionBot::actual();
        $provider = $config?->ai_provider ?: 'openai';

        Log::info('🤖 IA dispatch', [
            'provider' => $provider,
            'mensajes' => count($messages),
            'tools'    => count($tools ?? []),
        ]);

        if ($provider === 'anthropic') {
            return $this->chatAnthropic($messages, $toolChoice, $tools, $opts, $config);
        }

        // Default: OpenAI
        return $this->chatOpenAI($messages, $toolChoice, $tools, $opts, $config);
    }

    /**
     * Llamada vía Anthropic Claude.
     */
    private function chatAnthropic(array $messages, $toolChoice, ?array $tools, array $opts, $config): ?array
    {
        $apiKey = $opts['apiKey'] ?? Tenant::resolverAnthropicKey();
        if (empty($apiKey)) {
            $this->alertarKeyFaltante('anthropic');
            return null;
        }

        $modelo = $opts['model'] ?? ($config?->modelo_anthropic ?: 'claude-sonnet-4-6');

        return app(AnthropicService::class)->chat($messages, $toolChoice, $tools, [
            'model'       => $modelo,
            'temperature' => $opts['temperature'] ?? (float) ($config?->temperatura ?? 0.85),
            'max_tokens'  => $opts['max_tokens']  ?? (int) ($config?->max_tokens ?? 1024),
            'apiKey'      => $apiKey,
            'intentos'    => self::INTENTOS,
        ]);
    }

    /**
     * Llamada vía OpenAI (con reintentos + alertas como antes).
     */
    private function chatOpenAI(array $messages, $toolChoice, ?array $tools, array $opts, $config): ?array
    {
        $apiKey = $opts['apiKey'] ?? Tenant::resolverOpenaiKey();
        if (empty($apiKey)) {
            $this->alertarKeyFaltante('openai');
            return null;
        }

        $modelo = $opts['model'] ?? ($config?->modelo_openai ?: 'gpt-4o-mini');
        $intentos = self::INTENTOS;
        $ultimoStatus = null;
        $ultimoBody   = null;
        $ultimaExc    = null;

        $payload = [
            'model'             => $modelo,
            'messages'          => $messages,
            'temperature'       => (float) ($opts['temperature'] ?? $config?->temperatura ?? 0.85),
            'top_p'             => 0.9,
            'frequency_penalty' => 0.4,
            'presence_penalty'  => 0.4,
            'max_tokens'        => (int) ($opts['max_tokens'] ?? $config?->max_tokens ?? 700),
        ];

        // ⚠️ OpenAI rechaza 'tool_choice' si no se envían 'tools'. Solo los
        // incluimos cuando realmente hay herramientas (el widget web no usa).
        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = $toolChoice;
        }

        for ($i = 1; $i <= $intentos; $i++) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(35)
                    ->post(self::OPENAI_URL, $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                $ultimoStatus = $response->status();
                $ultimoBody   = $response->body();

                Log::warning("⚠️ OpenAI intento {$i} falló", [
                    'status' => $ultimoStatus,
                    'body'   => mb_substr($ultimoBody, 0, 300),
                ]);

                if (in_array($ultimoStatus, [401, 403], true)) break;
            } catch (\Throwable $e) {
                $ultimaExc = $e->getMessage();
                Log::warning("⚠️ OpenAI excepción intento {$i}", ['error' => $ultimaExc]);
            }

            if ($i < $intentos) {
                $espera = $ultimoStatus === 429 ? min(15, pow(2, $i) * 2) : pow(2, $i - 1);
                sleep($espera);
            }
        }

        try {
            $alertaService = app(BotAlertaService::class);
            if ($ultimaExc !== null && $ultimoStatus === null) {
                $alertaService->registrar(
                    BotAlerta::TIPO_OPENAI_TIMEOUT,
                    '⌛ Sin conexión a OpenAI',
                    "No fue posible contactar OpenAI tras {$intentos} intentos. Error: {$ultimaExc}",
                    BotAlerta::SEV_CRITICA,
                    null,
                    ['modelo' => $modelo, 'excepcion' => $ultimaExc]
                );
            } else {
                $alertaService->registrarErrorOpenAI($ultimoStatus, $ultimoBody, ['modelo' => $modelo]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar alerta IA: ' . $e->getMessage());
        }

        return null;
    }

    private function alertarKeyFaltante(string $provider): void
    {
        $tenant = app(TenantManager::class)->current();
        $tenantNombre = $tenant?->nombre ?? 'desconocido';

        try {
            app(BotAlertaService::class)->registrar(
                BotAlerta::TIPO_OPENAI_KEY,
                "🔑 API key de {$provider} no configurada para tenant {$tenantNombre}",
                "Ve a Configuración del Bot → Proveedor IA y configura la API key del proveedor seleccionado ({$provider}).",
                BotAlerta::SEV_CRITICA
            );
        } catch (\Throwable $e) {
            // no bloquear
        }

        Log::error("❌ {$provider} API key no resuelta", ['tenant' => $tenantNombre]);
    }
}
