<?php

namespace App\Services\Bots;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\Tenant;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🤖 BOT CIERRE — agente especializado UNA sola tarea: cerrar pedidos.
 *
 * Cuando el bot principal alucina ("verificando datos…", "te despachamos",
 * "queda lista tu compra") sin invocar `confirmar_pedido`, este servicio
 * toma el control:
 *
 *   1. Lee el estado estructurado del pedido en BD (verdad).
 *   2. Llama a OpenAI con un prompt MINI y solo UNA tool disponible:
 *      `confirmar_pedido` (tool_choice forzado).
 *   3. Devuelve el orderData listo para invocar guardarPedidoDesdeToolCall.
 *
 * Si el estado está COMPLETO sin ambigüedad, ni siquiera llama LLM:
 * arma el orderData directamente. Es a prueba de alucinación.
 *
 * Es la "Capa 2" del bot: el cierre transaccional.
 */
class BotCierreService
{
    /**
     * Intenta cerrar el pedido. Devuelve un array con:
     *   ['ok' => true, 'orderData' => [...], 'via' => 'estado_bd'|'llm_forzado']
     * o
     *   ['ok' => false, 'razon' => 'string', 'faltantes' => [...]]
     */
    public function intentarCierre(ConversacionWhatsapp $conv): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);

        // Caso 1: ya cerrado previamente, no volver a intentar
        if ($estado->confirmado_at && $estado->pedido_id) {
            return [
                'ok'      => false,
                'razon'   => 'ya_confirmado',
                'pedido_id' => $estado->pedido_id,
            ];
        }

        // Caso 2: estado completo → cerrar SIN LLM (lo más rápido y robusto)
        if ($estado->estaCompleto()) {
            Log::info('🤖 BotCierre: estado COMPLETO en BD — cierre directo sin LLM', [
                'conv_id'  => $conv->id,
                'estado_id'=> $estado->id,
            ]);
            return [
                'ok'        => true,
                'orderData' => $estado->aOrderData(),
                'via'       => 'estado_bd',
            ];
        }

        // Caso 3: estado incompleto → tratar de extraer faltantes con LLM mini
        $faltantes = $estado->camposFaltantes();

        // 🛡️ PRE-CHECK ANTI-PEDIDO-FANTASMA:
        // Si los productos están vacíos en estado Y el último mensaje del
        // cliente NO menciona productos/intención de pedir → ABORTAR.
        // Esto evita que el LLM mini, con tool_choice forzado, fabrique un
        // pedido leyendo el historial cuando el cliente solo dijo "Hola".
        if (empty($estado->productos) && !$this->mensajeRecienteMencionaPedido($conv)) {
            Log::warning('🛡️ BotCierre ABORTADO: cliente no expresó intención de pedido en este turno', [
                'conv_id'   => $conv->id,
                'faltantes' => $faltantes,
            ]);
            return [
                'ok'        => false,
                'razon'     => 'sin_intencion_de_pedido',
                'faltantes' => $faltantes,
            ];
        }

        // 🚫 LLM MINI DESACTIVADO — generaba loops infinitos.
        // Si el estado está incompleto, el bot principal pide los datos
        // faltantes en su turno normal. No tiene sentido forzar otra
        // llamada LLM aquí (terminaba pidiendo los mismos datos en bucle).
        Log::info('🤖 BotCierre: estado incompleto — devolviendo faltantes al flujo principal', [
            'conv_id'   => $conv->id,
            'faltantes' => $faltantes,
        ]);

        return [
            'ok'        => false,
            'razon'     => 'estado_incompleto',
            'faltantes' => $faltantes,
        ];
    }

    /**
     * 🛡️ Verifica que el cliente haya expresado intención de pedido en
     * los ÚLTIMOS 3 mensajes USER (post-reset si aplica).
     *
     * Heurística: busca verbos/sustantivos típicos de pedido:
     *   "quiero", "necesito", "pídeme", "envíame", "mándame", "regálame",
     *   "domicilio", "despacho", "pedido", "pídelo", "manda",
     *   un número seguido de unidad (libra, kilo, kg, lb, unidad, paquete),
     *   o nombre de producto del catálogo activo.
     *
     * Si el cliente solo dijo "hola", "gracias", emojis, etc → false.
     */
    private function mensajeRecienteMencionaPedido(ConversacionWhatsapp $conv): bool
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);
        $resetAt = $estado->updated_at; // marca temporal del último reset/update

        $msgs = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->where('rol', 'user')
            ->when($resetAt, fn ($q) => $q->where('created_at', '>=', $resetAt->copy()->subMinutes(2)))
            ->orderByDesc('id')
            ->limit(3)
            ->pluck('contenido')
            ->all();

        $texto = mb_strtolower(implode(' ', $msgs));

        if (trim($texto) === '') return false;

        // Patrones explícitos de intención
        $patrones = [
            '/\b(quiero|necesito|pideme|p[íi]deme|env[íi]ame|m[áa]ndame|reg[áa]lame|pedido|domicilio|despacho|pasame|p[áa]same|llevame|ll[ée]vame|haz|hag[áa]me|dame|regalame)\b/u',
            '/\b\d+\s*(kg|kilo|kilos|lb|libra|libras|gr|gramos|paquete|paquetes|und|unidad|unidades|porcion|porciones|pack)\b/u',
            '/\b(otro pedido|nuevo pedido|para un pedido|otra orden|hacer pedido)\b/u',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $texto)) return true;
        }

        // ¿Menciona algún producto del catálogo activo? (token >=4 chars)
        try {
            $catalogo = app(\App\Services\BotCatalogoService::class)->productosActivos();
            $tokens = collect();
            foreach ($catalogo as $p) {
                $palabras = preg_split('/\s+/', mb_strtolower(\Illuminate\Support\Str::ascii((string) $p->nombre)));
                foreach ($palabras as $w) {
                    if (mb_strlen($w) >= 5) $tokens->push($w);
                }
            }
            $tokensUnicos = $tokens->unique()->values();
            $textoNorm = mb_strtolower(\Illuminate\Support\Str::ascii($texto));
            foreach ($tokensUnicos as $t) {
                if (str_contains($textoNorm, $t)) return true;
            }
        } catch (\Throwable $e) {
            // Si el catálogo falla, ya con los patrones explícitos basta
        }

        return false;
    }

    /**
     * Llama a OpenAI con un prompt mini y tool_choice forzado a confirmar_pedido.
     * El LLM solo ve los datos del estado actual y los últimos 6 mensajes
     * de chat — no se le da contexto extra para que no se distraiga.
     */
    private function intentarConLlm(ConversacionWhatsapp $conv, ConversacionPedidoEstado $estado): ?array
    {
        $openaiKey = Tenant::resolverOpenaiKey();
        if (empty($openaiKey)) {
            Log::warning('🤖 BotCierre: sin OpenAI key — no puede usar LLM mini');
            return null;
        }

        // 🛡️ Últimos 6 mensajes pero SOLO posteriores al último reset
        // del estado. Así si hubo auto_reset, no contaminamos con el
        // pedido anterior.
        $resetAt = $estado->updated_at;

        $ultimos = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->whereIn('rol', ['user', 'assistant'])
            ->when($resetAt, fn ($q) => $q->where('created_at', '>=', $resetAt->copy()->subMinutes(2)))
            ->orderByDesc('id')
            ->limit(6)
            ->get(['rol', 'contenido'])
            ->reverse()
            ->values();

        $chatRecien = $ultimos->map(fn ($m) =>
            "[{$m->rol}]: " . mb_strimwidth((string) $m->contenido, 0, 200, '…')
        )->implode("\n");

        $estadoJson = [
            'productos'           => $estado->productos,
            'metodo_entrega'      => $estado->metodo_entrega,
            'sede'                => $estado->sede?->nombre,
            'sede_id'             => $estado->sede_id,
            'direccion'           => $estado->direccion,
            'barrio'              => $estado->barrio,
            'ciudad'              => $estado->ciudad,
            'cobertura_validada'  => $estado->cobertura_validada,
            'cedula'              => $estado->cedula,
            'nombre_cliente'      => $estado->nombre_cliente,
            'telefono'            => $estado->telefono,
            'cliente_existe_erp'  => $estado->cliente_existe_erp,
        ];

        $systemPrompt =
            "Eres BOT CIERRE: un agente determinista cuyo ÚNICO trabajo es invocar la función `confirmar_pedido` con los datos completos del pedido.\n\n"
            . "NO conversas con el cliente. NO respondes texto. NO haces preguntas.\n"
            . "Tu output válido es UNA sola cosa: la invocación de `confirmar_pedido`.\n\n"
            . "ESTADO ESTRUCTURADO DEL PEDIDO (verdad en BD):\n"
            . json_encode($estadoJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n"
            . "ÚLTIMOS MENSAJES DE LA CONVERSACIÓN:\n{$chatRecien}\n\n"
            . "REGLAS:\n"
            . "1. Si los productos están vacíos pero el cliente los mencionó en los últimos mensajes (ej: '5 libras de pierna de cerdo'), extráelos.\n"
            . "2. Si metodo_entrega es 'recoger', usa address='', location=nombre_de_la_sede, pickup=true, sede_id=el id.\n"
            . "3. Si metodo_entrega es 'domicilio', usa address=la_direccion, location=la_ciudad.\n"
            . "4. customer_name viene de nombre_cliente (puede ser del ERP).\n"
            . "5. cedula y phone vienen del estado.\n"
            . "6. Llama `confirmar_pedido` UNA sola vez con TODOS los datos.\n";

        $tools = $this->definicionToolConfirmarPedido();

        try {
            // 🤖 Usar el AiClientService para que respete el proveedor configurado
            // (OpenAI o Anthropic) según el tenant.
            $data = app(\App\Services\Ai\AiClientService::class)->chat(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => 'Cierra el pedido AHORA llamando a confirmar_pedido con los datos del estado y los últimos mensajes.'],
                ],
                ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']],
                $tools,
                ['temperature' => 0.1, 'max_tokens' => 500]
            );

            if (!$data) {
                Log::warning('🤖 BotCierre: el proveedor IA falló');
                return null;
            }

            $toolCalls = $data['choices'][0]['message']['tool_calls'] ?? null;

            if (!$toolCalls || ($toolCalls[0]['function']['name'] ?? '') !== 'confirmar_pedido') {
                Log::warning('🤖 BotCierre: LLM no invocó confirmar_pedido', [
                    'message' => $data['choices'][0]['message'] ?? null,
                ]);
                return null;
            }

            $orderData = json_decode($toolCalls[0]['function']['arguments'] ?? '{}', true) ?: [];

            // Sanitizar productos
            $orderData['products'] = array_values(array_filter(
                $orderData['products'] ?? [],
                fn ($p) => !empty($p['name'])
            ));

            if (empty($orderData['products'])) {
                Log::warning('🤖 BotCierre: LLM extrajo orderData sin productos', ['orderData' => $orderData]);
                return null;
            }

            return $orderData;
        } catch (\Throwable $e) {
            Log::error('🤖 BotCierre: excepción al llamar OpenAI: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Definición SOLO de la tool confirmar_pedido para el bot de cierre.
     * Es una versión reducida — el bot mini no necesita ver las 15 tools
     * del bot principal.
     */
    private function definicionToolConfirmarPedido(): array
    {
        return [[
            'type' => 'function',
            'function' => [
                'name' => 'confirmar_pedido',
                'description' => 'Registrar el pedido del cliente con todos los datos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'products' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name'     => ['type' => 'string'],
                                    'quantity' => ['type' => 'number'],
                                    'unit'     => ['type' => 'string'],
                                ],
                                'required' => ['name', 'quantity'],
                            ],
                        ],
                        'customer_name'  => ['type' => 'string'],
                        'cedula'         => ['type' => 'string'],
                        'phone'          => ['type' => 'string'],
                        'address'        => ['type' => 'string'],
                        'neighborhood'   => ['type' => 'string'],
                        'location'       => ['type' => 'string'],
                        'pickup'         => ['type' => 'boolean'],
                        'sede_id'        => ['type' => 'integer'],
                        'payment_method' => ['type' => 'string'],
                        'notes'          => ['type' => 'string'],
                    ],
                    'required' => ['products'],
                ],
            ],
        ]];
    }
}
