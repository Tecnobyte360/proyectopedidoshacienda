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
        // Último mensaje del cliente que el BOT aún no respondió. Sugerimos
        // aunque un operador humano haya escrito después (modo entrenamiento):
        // así el copiloto siempre propone para cada pregunta del cliente.
        $ultimoCliente = $this->ultimoClienteSinRespuestaBot($conv);

        if (!$ultimoCliente) return null;

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

    /**
     * Devuelve el último mensaje del CLIENTE que el BOT (rol=assistant generado
     * por IA, no operador humano) aún no respondió. Devuelve null si el bot ya
     * contestó después del último mensaje del cliente.
     *
     * 🎯 MODO ENTRENAMIENTO: ignoramos los mensajes de operadores HUMANOS. Es
     * decir, si un humano escribió pero el bot no, igual sugerimos — para que el
     * copiloto practique con cada pregunta real del cliente.
     */
    public function ultimoClienteSinRespuestaBot(ConversacionWhatsapp $conv): ?MensajeWhatsapp
    {
        $ultimoCliente = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->where('rol', 'user')
            ->orderByDesc('id')
            ->first();

        if (!$ultimoCliente) return null;

        // ¿Hay una respuesta del BOT (IA, no humano) DESPUÉS de ese mensaje?
        // meta->enviado_por_humano = true marca los mensajes del operador.
        $respuestaBotPosterior = MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->where('rol', 'assistant')
            ->where('id', '>', $ultimoCliente->id)
            ->get()
            ->first(function ($m) {
                $meta = is_array($m->meta) ? $m->meta : (json_decode((string) $m->meta, true) ?: []);
                // Es respuesta del BOT si NO fue enviada por un humano.
                return empty($meta['enviado_por_humano']);
            });

        // Si el bot ya respondió → no sugerimos (ya está atendido por IA).
        if ($respuestaBotPosterior) return null;

        return $ultimoCliente;
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

🛒 CATÁLOGO Y PRECIOS — USÁ LAS HERRAMIENTAS, NUNCA INVENTES:
Tenés herramientas para CONSULTAR el catálogo REAL del negocio (productos,
precios, categorías). REGLAS DE ORO:
- Si el cliente pregunta por un producto, su precio o qué hay, PRIMERO llamá
  la herramienta correspondiente (buscar_productos / listar_categorias /
  productos_de_categoria) y respondé SOLO con lo que devuelva.
- PROHIBIDO listar productos o precios de memoria. PROHIBIDO inventar cifras.
- Si la herramienta NO encuentra el producto, decí honestamente que no lo
  manejás o pedí que lo confirme — NUNCA te lo inventes.
- Usá los precios EXACTOS que devuelva la herramienta (precio_kg, precio_libra,
  etc. según la unidad). No hagas cuentas raras.

OTRAS REGLAS:
- Seguí las lecciones de abajo al pie de la letra.
- Respondé SOLO el mensaje que le mandarías al cliente, sin explicaciones.
- Sé breve, cálido y colombiano, como el mejor operador del equipo.

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

            // 🛒 Tools de SOLO LECTURA del catálogo real (HGI / catálogo local).
            // El copiloto puede CONSULTAR productos y precios reales, pero NUNCA
            // ejecuta acciones de escritura (no crea pedidos, no factura, no envía).
            $tools  = $this->toolsCatalogoSoloLectura();
            $sedeId = $this->resolverSedeId($conv);

            // Loop de function-calling acotado (máx 4 vueltas) — solo lectura.
            $contenido = '';
            for ($i = 0; $i < 4; $i++) {
                $resp = $this->ai->chat(
                    messages: $messages,
                    toolChoice: 'auto',   // puede pedir consultar catálogo
                    tools: $tools,
                    opts: ['provider' => 'anthropic', 'temperature' => 0.4, 'max_tokens' => 600],
                );

                $msg       = $resp['choices'][0]['message'] ?? [];
                $toolCalls = $msg['tool_calls'] ?? [];
                $contenido = trim((string) ($msg['content'] ?? ''));

                // Sin tool_calls → respuesta final de texto, terminamos.
                if (empty($toolCalls)) {
                    break;
                }

                // Reinyectar el mensaje del asistente (con los tool_calls) y luego
                // los resultados de cada tool, para que el modelo redacte con datos reales.
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $msg['content'] ?? null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $tc) {
                    $name    = $tc['function']['name'] ?? '';
                    $rawArgs = $tc['function']['arguments'] ?? '{}';
                    $args    = json_decode($rawArgs, true) ?: [];
                    $tcId    = $tc['id'] ?? ('call_' . uniqid());

                    $resultado = $this->ejecutarToolCatalogo($name, $args, $sedeId);

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $tcId,
                        'name'         => $name,
                        'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            return $contenido;
        } catch (\Throwable $e) {
            Log::warning('Bot shadow: fallo generando sugerencia', [
                'conv' => $conv->id, 'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * 🛒 Definición de las tools de SOLO LECTURA del catálogo (formato OpenAI).
     * Son EXACTAMENTE las que usa el bot real, pero excluye toda escritura
     * (crear_pedido, crear_adicion_pedido, etc). Imposible que modifique nada.
     */
    private function toolsCatalogoSoloLectura(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'buscar_productos',
                    'description' => 'Busca productos REALES del catálogo por nombre/palabra. '
                        . 'Úsala SIEMPRE antes de hablar de un producto o su precio. '
                        . 'Pasá el texto literal del cliente. Retorna código, nombre, categoría, precio y unidad.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query'     => ['type' => 'string', 'description' => 'Texto a buscar (ej: "costilla", "pollo", "queso").'],
                            'categoria' => ['type' => 'string', 'description' => 'Categoría opcional para acotar.'],
                            'limite'    => ['type' => 'integer', 'description' => 'Máx resultados (default 5).'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'listar_categorias',
                    'description' => 'Lista las categorías reales del catálogo con cuántos productos hay en cada una. '
                        . 'Úsala cuando el cliente pregunte "qué tienen", "qué venden", "el menú" o esté indeciso.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'productos_de_categoria',
                    'description' => 'Lista los productos reales de una categoría. Ej: "muéstrame las carnes de res".',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'categoria' => ['type' => 'string', 'description' => 'Nombre de la categoría (exacto o parcial).'],
                            'limite'    => ['type' => 'integer', 'description' => 'Máx resultados (default 20).'],
                        ],
                        'required' => ['categoria'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'info_producto',
                    'description' => 'Detalle de un producto por código: descripción, cortes disponibles, precio.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'codigo' => ['type' => 'string', 'description' => 'Código del producto.'],
                        ],
                        'required' => ['codigo'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'productos_destacados',
                    'description' => 'Productos destacados / promociones reales. Útil cuando el cliente está perdido.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limite' => ['type' => 'integer', 'description' => 'Máx resultados (default 8).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Ejecuta una tool de catálogo de SOLO LECTURA. Reusa el mismo motor que el
     * bot real (BotCatalogoToolService). Cualquier nombre no listado se ignora
     * (devuelve error) — el copiloto NO puede ejecutar acciones de escritura.
     */
    private function ejecutarToolCatalogo(string $name, array $args, ?int $sedeId): array
    {
        try {
            $svc = app(\App\Services\BotCatalogoToolService::class);

            return match ($name) {
                'buscar_productos' => $svc->buscarProductos(
                    (string) ($args['query'] ?? ''),
                    $args['categoria'] ?? null,
                    (int) ($args['limite'] ?? 5),
                    $sedeId
                ),
                'listar_categorias' => $svc->listarCategorias($sedeId),
                'productos_de_categoria' => $svc->productosDeCategoria(
                    (string) ($args['categoria'] ?? ''),
                    (int) ($args['limite'] ?? 20),
                    $sedeId
                ),
                'info_producto' => $svc->infoProducto((string) ($args['codigo'] ?? ''), $sedeId),
                'productos_destacados' => $svc->productosDestacados((int) ($args['limite'] ?? 8), $sedeId),
                // 🔒 Cualquier otra tool (escritura) NO está disponible en el copiloto.
                default => ['error' => "Herramienta '{$name}' no disponible en modo entrenamiento (solo lectura)."],
            };
        } catch (\Throwable $e) {
            Log::warning('Bot shadow: fallo ejecutando tool catálogo', [
                'tool' => $name, 'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Resuelve la sede para consultar precios. Si la conversación tiene sede
     * asociada la usa; si no, null (el motor hace fallback a precio base).
     */
    private function resolverSedeId(ConversacionWhatsapp $conv): ?int
    {
        try {
            foreach (['sede_id', 'sede'] as $attr) {
                if (isset($conv->{$attr}) && is_numeric($conv->{$attr})) {
                    return (int) $conv->{$attr};
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
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
