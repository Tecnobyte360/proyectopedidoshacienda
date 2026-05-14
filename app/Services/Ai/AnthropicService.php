<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🤖 Cliente de Anthropic Claude (Messages API).
 *
 * Recibe mensajes en formato OpenAI (role/content/tool_calls) y los
 * traduce internamente al formato Anthropic. Devuelve respuesta
 * normalizada al formato OpenAI para que el resto del código funcione
 * sin cambios.
 *
 * Formato Anthropic:
 *   - system se pasa como parámetro separado (no en messages)
 *   - messages solo tienen role: user | assistant
 *   - tool_use va dentro de content como bloques estructurados
 *   - tool_choice: {type: "auto"} | {type: "any"} | {type: "tool", name: "..."} | {type: "none"}
 */
class AnthropicService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * Modelos soportados (orden = preferencia).
     */
    public const MODELOS = [
        'claude-opus-4-7'        => 'Claude Opus 4.7 (más inteligente, más lento)',
        'claude-sonnet-4-6'      => 'Claude Sonnet 4.6 (balance recomendado)',
        'claude-haiku-4-5'       => 'Claude Haiku 4.5 (rápido y económico)',
        'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku-latest'  => 'Claude 3.5 Haiku',
    ];

    /**
     * Llama a la API de Anthropic. Recibe los mismos parámetros que
     * el método existente de OpenAI y devuelve respuesta en formato
     * compatible con OpenAI (choices[0].message.{content,tool_calls}).
     *
     * @param  array  $messages  Mensajes en formato OpenAI
     * @param  mixed  $toolChoice 'auto'|'required'|'none'|['type'=>'function','function'=>['name'=>'X']]
     * @param  array|null  $tools  Tools en formato OpenAI
     * @param  array  $opts  ['model'=>..., 'temperature'=>..., 'max_tokens'=>..., 'apiKey'=>...]
     * @return array|null  Respuesta normalizada en formato OpenAI o null si falla
     */
    public function chat(array $messages, $toolChoice = 'auto', ?array $tools = null, array $opts = []): ?array
    {
        $apiKey = $opts['apiKey'] ?? null;
        if (empty($apiKey)) {
            Log::warning('🤖 Anthropic: API key no resuelta');
            return null;
        }

        $model = $opts['model'] ?? 'claude-sonnet-4-6';
        $temperature = (float) ($opts['temperature'] ?? 0.85);
        $maxTokens = (int) ($opts['max_tokens'] ?? 1024);
        $intentos = (int) ($opts['intentos'] ?? 3);

        // Traducir formato OpenAI → Anthropic
        [$systemAnt, $messagesAnt] = $this->traducirMessages($messages);
        $toolsAnt = $this->traducirTools($tools);
        $toolChoiceAnt = $this->traducirToolChoice($toolChoice);

        $payload = [
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'messages'    => $messagesAnt,
        ];

        // 💰 PROMPT CACHING — system prompt + tools se cachean 5 min en Anthropic.
        // Reduce ~90% del costo y NO cuenta hacia el rate limit de input tokens.
        // System debe ir como array con cache_control.
        if ($systemAnt !== '') {
            $payload['system'] = [
                [
                    'type'          => 'text',
                    'text'          => $systemAnt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ];
        }
        if (!empty($toolsAnt)) {
            // Cachear también la definición de tools (es grande y repetitiva)
            $lastIdx = count($toolsAnt) - 1;
            if ($lastIdx >= 0) {
                $toolsAnt[$lastIdx]['cache_control'] = ['type' => 'ephemeral'];
            }
            $payload['tools'] = $toolsAnt;
            if ($toolChoiceAnt) $payload['tool_choice'] = $toolChoiceAnt;
        }

        $ultimoStatus = null;
        $ultimoBody = null;
        $ultimaExc = null;

        $tStart = microtime(true);
        $intentosReales = 0;

        for ($i = 1; $i <= $intentos; $i++) {
            $intentosReales = $i;
            try {
                $response = Http::withHeaders([
                        'x-api-key'         => $apiKey,
                        'anthropic-version' => self::API_VERSION,
                        'content-type'      => 'application/json',
                    ])
                    ->timeout(45)
                    ->post(self::API_URL, $payload);

                if ($response->successful()) {
                    $latMs = (int) ((microtime(true) - $tStart) * 1000);
                    $this->registrarInvocacion(
                        model: $model,
                        httpStatus: 200,
                        exitoso: true,
                        latenciaMs: $latMs,
                        intentos: $intentosReales,
                        respuestaJson: $response->json(),
                        messagesCount: count($messagesAnt),
                        toolsCount: count($toolsAnt),
                        esFallback: !empty($opts['_isFallback'])
                    );
                    return $this->traducirRespuesta($response->json());
                }

                $ultimoStatus = $response->status();
                $ultimoBody   = $response->body();

                Log::warning("⚠️ Anthropic intento {$i} falló", [
                    'status' => $ultimoStatus,
                    'body'   => mb_substr($ultimoBody, 0, 500),
                ]);

                // 401/403: no reintentar
                if (in_array($ultimoStatus, [401, 403], true)) break;

                // 🛡️ 400 con "tool_use" sin "tool_result" → sanitizar y reintentar
                // INMEDIATAMENTE (no esperar a fallar todos los intentos).
                // Sin sanitizar, los 4 reintentos fallan exactamente igual.
                if ($ultimoStatus === 400
                    && stripos($ultimoBody, 'tool_use') !== false
                    && stripos($ultimoBody, 'tool_result') !== false
                    && empty($opts['_sanitizedRetry'])) {
                    Log::warning('🛡️ 400 tool_use huérfano detectado — sanitizando y reintentando una vez', [
                        'status' => $ultimoStatus,
                    ]);
                    $messagesLimpios = $this->sanitizarMensajesParaFallback($messages);
                    return $this->chat($messagesLimpios, $toolChoice, $tools, array_merge($opts, [
                        '_sanitizedRetry' => true,
                        'intentos'        => 2,
                    ]));
                }
            } catch (\Throwable $e) {
                $ultimaExc = $e->getMessage();
                Log::warning("⚠️ Anthropic excepción intento {$i}", ['error' => $ultimaExc]);
            }

            if ($i < $intentos) {
                $esperaSegs = $ultimoStatus === 429 ? min(15, pow(2, $i) * 2) : pow(2, $i - 1);
                sleep($esperaSegs);
            }
        }

        Log::error('❌ Anthropic falló todos los intentos', [
            'status' => $ultimoStatus,
            'modelo' => $model,
        ]);

        // Registrar fallo final
        $latMs = (int) ((microtime(true) - $tStart) * 1000);
        $this->registrarInvocacion(
            model: $model,
            httpStatus: $ultimoStatus,
            exitoso: false,
            latenciaMs: $latMs,
            intentos: $intentosReales,
            errorBody: $ultimoBody ?? $ultimaExc,
            messagesCount: count($messagesAnt),
            toolsCount: count($toolsAnt),
            esFallback: !empty($opts['_isFallback'])
        );

        // 🔄 FALLBACK AUTOMÁTICO: si el modelo principal está overloaded (529),
        // probamos con Haiku que tiene más capacidad disponible. Solo si no
        // estamos ya usando Haiku (para evitar recursión).
        $esOverloaded = $ultimoStatus === 529
            || (is_string($ultimoBody) && stripos($ultimoBody, 'overloaded') !== false);
        $yaEsHaiku = stripos($model, 'haiku') !== false;

        if ($esOverloaded && !$yaEsHaiku && empty($opts['_isFallback'])) {
            Log::warning('🔄 Anthropic overloaded — fallback a Haiku', [
                'modelo_original' => $model,
            ]);
            // Sanitizar mensajes para evitar tool_use sin tool_result inmediato
            // (esto rompe Anthropic con error 400 al reintentar con otro modelo).
            $messagesLimpios = $this->sanitizarMensajesParaFallback($messages);
            return $this->chat($messagesLimpios, $toolChoice, $tools, array_merge($opts, [
                'model'        => 'claude-haiku-4-5',
                'intentos'     => 2,
                '_isFallback'  => true,
            ]));
        }

        return null;
    }

    /**
     * Registra cada llamada al modelo en `llm_invocaciones` para que la
     * UI de monitoreo pueda mostrar paso a paso qué pasa con Anthropic.
     */
    private function registrarInvocacion(
        string $model,
        ?int $httpStatus,
        bool $exitoso,
        int $latenciaMs,
        int $intentos,
        ?array $respuestaJson = null,
        ?string $errorBody = null,
        int $messagesCount = 0,
        int $toolsCount = 0,
        bool $esFallback = false
    ): void {
        try {
            $usage = $respuestaJson['usage'] ?? [];
            $errorTipo = null;
            $errorMsg = null;
            if (!$exitoso && $errorBody) {
                $decoded = json_decode($errorBody, true);
                if (is_array($decoded)) {
                    $errorTipo = $decoded['error']['type'] ?? null;
                    $errorMsg  = $decoded['error']['message'] ?? null;
                } else {
                    $errorMsg = mb_substr((string) $errorBody, 0, 500);
                }
            }

            $tm   = app(\App\Services\TenantManager::class);
            $tenantId = method_exists($tm, 'id') ? $tm->id() : null;

            \App\Models\LlmInvocacion::create([
                'tenant_id'              => $tenantId,
                'provider'               => 'anthropic',
                'modelo'                 => $model,
                'es_fallback'            => $esFallback,
                'http_status'            => $httpStatus,
                'exitoso'                => $exitoso,
                'error_tipo'             => $errorTipo,
                'error_mensaje'          => $errorMsg ? mb_substr($errorMsg, 0, 500) : null,
                'tokens_input'           => $usage['input_tokens'] ?? null,
                'tokens_output'          => $usage['output_tokens'] ?? null,
                'tokens_cache_read'      => $usage['cache_read_input_tokens'] ?? null,
                'tokens_cache_creation'  => $usage['cache_creation_input_tokens'] ?? null,
                'latencia_ms'            => $latenciaMs,
                'intentos'               => $intentos,
                'messages_count'         => $messagesCount,
                'tools_count'            => $toolsCount,
            ]);
        } catch (\Throwable $e) {
            // No bloquear si falla el log de monitoreo
            Log::warning('No se pudo registrar invocacion LLM: ' . $e->getMessage());
        }
    }

    /**
     * Quita tool_calls del assistant si no hay un mensaje tool inmediato
     * después. Anthropic exige que cada tool_use tenga su tool_result
     * en el siguiente mensaje, si no, rechaza con 400.
     */
    private function sanitizarMensajesParaFallback(array $messages): array
    {
        $limpios = [];
        $count = count($messages);
        for ($i = 0; $i < $count; $i++) {
            $msg = $messages[$i];
            $role = $msg['role'] ?? '';
            $hasToolCalls = $role === 'assistant' && !empty($msg['tool_calls']);
            if ($hasToolCalls) {
                $next = $messages[$i + 1] ?? null;
                $nextEsTool = $next && ($next['role'] ?? '') === 'tool';
                if (!$nextEsTool) {
                    // Eliminar tool_calls — el assistant sigue solo con su content
                    unset($msg['tool_calls']);
                    if (empty(trim((string) ($msg['content'] ?? '')))) {
                        // No hay nada que decir, saltarlo
                        continue;
                    }
                }
            }
            $limpios[] = $msg;
        }
        return $limpios;
    }

    /**
     * Traduce array messages OpenAI → [system_string, messages_anthropic].
     * - Concatena todos los `role: system` en un solo string.
     * - Convierte `role: tool` a un mensaje user con content tool_result.
     * - Convierte assistant.tool_calls a content blocks tool_use.
     */
    private function traducirMessages(array $messages): array
    {
        $systems = [];
        $out = [];
        $lastWasUser = false;

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            $toolCalls = $m['tool_calls'] ?? null;

            if ($role === 'system') {
                if (is_string($content) && $content !== '') {
                    $systems[] = $content;
                }
                continue;
            }

            if ($role === 'tool') {
                // Anthropic: tool_result va como user message con content array
                $toolCallId = $m['tool_call_id'] ?? '';
                $out[] = [
                    'role' => 'user',
                    'content' => [[
                        'type'        => 'tool_result',
                        'tool_use_id' => $toolCallId,
                        'content'     => is_string($content) ? $content : json_encode($content),
                    ]],
                ];
                continue;
            }

            if ($role === 'assistant') {
                $blocks = [];
                if (is_string($content) && $content !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $content];
                }
                if (is_array($toolCalls) && !empty($toolCalls)) {
                    foreach ($toolCalls as $tc) {
                        $name = $tc['function']['name'] ?? '';
                        $args = $tc['function']['arguments'] ?? '{}';
                        $argsArr = is_string($args) ? (json_decode($args, true) ?: []) : (array) $args;
                        $blocks[] = [
                            'type'  => 'tool_use',
                            'id'    => $tc['id'] ?? ('toolu_' . uniqid()),
                            'name'  => $name,
                            'input' => empty($argsArr) ? new \stdClass() : $argsArr,
                        ];
                    }
                }
                if (empty($blocks)) {
                    $blocks[] = ['type' => 'text', 'text' => '...'];
                }
                $out[] = ['role' => 'assistant', 'content' => $blocks];
                continue;
            }

            // role === 'user' (o cualquier otro)
            $contentStr = is_string($content) ? $content : json_encode($content);
            if ($contentStr === '') continue;
            $out[] = ['role' => 'user', 'content' => $contentStr];
        }

        // 🛡️ Anthropic exige AL MENOS UN mensaje. Si todos eran system o
        // se filtraron, agregar uno user mínimo para no fallar con 400.
        if (empty($out)) {
            $out[] = ['role' => 'user', 'content' => 'Hola'];
        }

        // Anthropic exige que el primer mensaje sea user
        if (($out[0]['role'] ?? '') !== 'user') {
            array_unshift($out, ['role' => 'user', 'content' => 'Hola']);
        }

        // 🛡️ FIX RAÍZ: garantizar que cada `tool_use` tenga su `tool_result` INMEDIATO.
        // Anthropic exige: si un assistant message tiene N tool_use blocks, el
        // siguiente user message DEBE contener N tool_result blocks (uno por cada id).
        // Si falta alguno, agregamos un tool_result placeholder para evitar el 400.
        $out = $this->garantizarToolResults($out);

        return [implode("\n\n", $systems), $out];
    }

    /**
     * 🛡️ Recorre los mensajes ya traducidos a formato Anthropic y, para cada
     * assistant con uno o más `tool_use`, verifica que el mensaje siguiente sea
     * un user con tool_result blocks para TODOS los ids. Si falta alguno, lo
     * inserta como tool_result vacío para que Anthropic no rechace con 400.
     */
    private function garantizarToolResults(array $messages): array
    {
        // 🛡️ PASO 1: deduplicar tool_result en cada user message.
        // Si el flujo upstream agregó manualmente un tool_result Y luego el
        // traductor procesó un role:tool con el mismo id, se duplica el bloque.
        // Anthropic rechaza con 400: "each tool_use must have a single result".
        foreach ($messages as $idx => $msg) {
            if (($msg['role'] ?? '') !== 'user') continue;
            if (!is_array($msg['content'] ?? null)) continue;

            $visto = [];
            $limpio = [];
            $huboDup = false;
            foreach ($msg['content'] as $b) {
                if (is_array($b) && ($b['type'] ?? '') === 'tool_result' && !empty($b['tool_use_id'])) {
                    $id = $b['tool_use_id'];
                    if (isset($visto[$id])) {
                        $huboDup = true;
                        continue; // skip el duplicado
                    }
                    $visto[$id] = true;
                }
                $limpio[] = $b;
            }
            if ($huboDup) {
                \Illuminate\Support\Facades\Log::warning('🛡️ tool_result duplicado(s) eliminado(s)', [
                    'msg_index' => $idx,
                    'ids_unicos' => array_keys($visto),
                ]);
                $messages[$idx]['content'] = $limpio;
            }
        }

        $count = count($messages);

        for ($i = 0; $i < $count; $i++) {
            $msg = $messages[$i];

            if (($msg['role'] ?? '') !== 'assistant') continue;
            $blocks = $msg['content'] ?? [];
            if (!is_array($blocks)) continue;

            $toolUseIds = [];
            foreach ($blocks as $b) {
                if (is_array($b) && ($b['type'] ?? '') === 'tool_use' && !empty($b['id'])) {
                    $toolUseIds[] = $b['id'];
                }
            }
            if (empty($toolUseIds)) continue;

            $next = $messages[$i + 1] ?? null;
            $nextEsUserConToolResult = $next
                && ($next['role'] ?? '') === 'user'
                && is_array($next['content'] ?? null)
                && collect($next['content'])->contains(fn ($b) => is_array($b) && ($b['type'] ?? '') === 'tool_result');

            $existentes = [];
            if ($nextEsUserConToolResult) {
                foreach ($next['content'] as $b) {
                    if (is_array($b) && ($b['type'] ?? '') === 'tool_result' && !empty($b['tool_use_id'])) {
                        $existentes[] = $b['tool_use_id'];
                    }
                }
            }

            $faltantes = array_values(array_diff($toolUseIds, $existentes));
            if (empty($faltantes)) continue;

            \Illuminate\Support\Facades\Log::warning('🛡️ Insertando tool_result placeholder para tool_use huérfanos', [
                'tool_use_ids_total'     => $toolUseIds,
                'tool_use_ids_faltantes' => $faltantes,
                'tenia_user_siguiente'   => $nextEsUserConToolResult,
            ]);

            if ($nextEsUserConToolResult) {
                $contentNext = $messages[$i + 1]['content'];
                foreach ($faltantes as $idFalt) {
                    $contentNext[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $idFalt,
                        'content'     => '(sin resultado disponible)',
                    ];
                }
                $messages[$i + 1]['content'] = $contentNext;
            } else {
                // Insertar un nuevo user con tool_results dummy justo después del assistant
                $nuevoUser = [
                    'role'    => 'user',
                    'content' => array_map(fn ($id) => [
                        'type'        => 'tool_result',
                        'tool_use_id' => $id,
                        'content'     => '(sin resultado disponible)',
                    ], $toolUseIds),
                ];
                array_splice($messages, $i + 1, 0, [$nuevoUser]);
                $count++;
            }
        }

        return $messages;
    }

    /**
     * Traduce tools OpenAI → Anthropic.
     * OpenAI: [{type: "function", function: {name, description, parameters}}]
     * Anthropic: [{name, description, input_schema}]
     *
     * 🛡️ Anthropic es ESTRICTO con keys: solo [a-zA-Z0-9_.-] (max 64 chars).
     * Las sanitizamos para evitar errores 400.
     */
    private function traducirTools(?array $tools): array
    {
        if (empty($tools)) return [];
        $out = [];
        foreach ($tools as $t) {
            $fn = $t['function'] ?? $t;
            $rawName = (string) ($fn['name'] ?? 'tool');
            $name = $this->sanitizarKey($rawName) ?: 'tool';

            $schema = $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()];
            $schema = $this->sanitizarSchemaParaAnthropic($schema);

            $out[] = [
                'name'         => $name,
                'description'  => (string) ($fn['description'] ?? ''),
                'input_schema' => $schema,
            ];
        }
        return $out;
    }

    /**
     * Sanitiza un schema JSONSchema para Anthropic:
     *   - Renombra keys de properties que no matchen [a-zA-Z0-9_.-]{1,64}.
     *   - Trunca keys >64 chars.
     *   - Sincroniza el array `required` con las nuevas keys.
     */
    private function sanitizarSchemaParaAnthropic($schema): array
    {
        if (!is_array($schema)) {
            return ['type' => 'object', 'properties' => new \stdClass()];
        }

        // Ensure type
        if (!isset($schema['type'])) $schema['type'] = 'object';

        if (isset($schema['properties']) && (is_array($schema['properties']) || is_object($schema['properties']))) {
            $props = (array) $schema['properties'];
            $newProps = [];
            $rename = [];
            foreach ($props as $key => $val) {
                $newKey = $this->sanitizarKey((string) $key);
                if ($newKey === '') $newKey = 'param_' . count($newProps);
                if ($newKey !== $key) $rename[$key] = $newKey;
                // Recursivo si es schema anidado
                if (is_array($val) && isset($val['properties'])) {
                    $val = $this->sanitizarSchemaParaAnthropic($val);
                }
                if (is_array($val) && ($val['type'] ?? null) === 'array' && isset($val['items']['properties'])) {
                    $val['items'] = $this->sanitizarSchemaParaAnthropic($val['items']);
                }
                $newProps[$newKey] = $val;
            }

            // 🛡️ Si properties queda VACÍO, debe ser objeto JSON ({}) no array ([])
            // PHP serializa array vacío como [] pero Anthropic exige {}.
            $schema['properties'] = empty($newProps) ? new \stdClass() : $newProps;

            // Sincronizar required
            if (isset($schema['required']) && is_array($schema['required'])) {
                $schema['required'] = array_values(array_filter(array_map(
                    fn ($r) => $rename[$r] ?? $this->sanitizarKey((string) $r),
                    $schema['required']
                )));
                // Si required queda vacío, removerlo (no enviarlo)
                if (empty($schema['required'])) unset($schema['required']);
            }
        } else {
            // properties vacío debe ser objeto, no array
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    /**
     * Sanitiza una key para Anthropic: solo [a-zA-Z0-9_.-], máximo 64 chars.
     */
    private function sanitizarKey(string $key): string
    {
        // Reemplazar todo lo que no sea válido por _
        $clean = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $key);
        // Colapsar __
        $clean = preg_replace('/_+/', '_', $clean);
        // Trim _ del inicio/final
        $clean = trim($clean, '_');
        // Truncar
        if (mb_strlen($clean) > 64) $clean = mb_substr($clean, 0, 64);
        return $clean;
    }

    /**
     * Traduce tool_choice OpenAI → Anthropic.
     * OpenAI 'auto'      → Anthropic ['type'=>'auto']
     * OpenAI 'required'  → Anthropic ['type'=>'any']
     * OpenAI 'none'      → Anthropic ['type'=>'none']  (se ignora — Anthropic lo logra omitiendo tools)
     * OpenAI {type:'function', function:{name:'X'}} → Anthropic ['type'=>'tool','name'=>'X']
     */
    private function traducirToolChoice($toolChoice): ?array
    {
        if ($toolChoice === 'auto' || $toolChoice === null) return ['type' => 'auto'];
        if ($toolChoice === 'required') return ['type' => 'any'];
        if ($toolChoice === 'none') return null;
        if (is_array($toolChoice) && ($toolChoice['type'] ?? '') === 'function') {
            $name = $toolChoice['function']['name'] ?? '';
            if ($name) return ['type' => 'tool', 'name' => $name];
        }
        return ['type' => 'auto'];
    }

    /**
     * Traduce respuesta Anthropic → formato OpenAI.
     * Anthropic devuelve content array con blocks de tipo 'text' y 'tool_use'.
     * Los unimos: el text concatenado en message.content, los tool_use en tool_calls.
     */
    private function traducirRespuesta(array $resp): array
    {
        $contentBlocks = $resp['content'] ?? [];
        $textOut = '';
        $toolCalls = [];

        foreach ($contentBlocks as $b) {
            $type = $b['type'] ?? '';
            if ($type === 'text') {
                $textOut .= ($b['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id'   => $b['id'] ?? 'toolu_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name'      => $b['name'] ?? '',
                        'arguments' => json_encode($b['input'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }
        }

        $message = [
            'role'    => 'assistant',
            'content' => $textOut === '' ? null : $textOut,
        ];
        if (!empty($toolCalls)) $message['tool_calls'] = $toolCalls;

        return [
            'choices' => [[
                'index'         => 0,
                'message'       => $message,
                'finish_reason' => $this->mapearStopReason($resp['stop_reason'] ?? null),
            ]],
            'model' => $resp['model'] ?? '',
            'usage' => [
                'prompt_tokens'     => $resp['usage']['input_tokens']  ?? 0,
                'completion_tokens' => $resp['usage']['output_tokens'] ?? 0,
                'total_tokens'      => ($resp['usage']['input_tokens']  ?? 0) + ($resp['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    private function mapearStopReason(?string $stopReason): string
    {
        return match ($stopReason) {
            'end_turn'    => 'stop',
            'tool_use'    => 'tool_calls',
            'max_tokens'  => 'length',
            'stop_sequence' => 'stop',
            default       => 'stop',
        };
    }
}
