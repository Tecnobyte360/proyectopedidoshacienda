<?php

namespace App\Services;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;

/**
 * 🎯 ORQUESTADOR DETERMINISTA DEL FLUJO DE PEDIDO
 *
 * Filosofía: al LLM no le damos libertad. En cada paso del flujo solo
 * puede hacer LO CORRECTO porque solo le dejamos ver las tools relevantes
 * a ese paso. Si en el paso CONFIRMACIÓN solo hay 1 tool disponible
 * (confirmar_pedido) y el tool_choice es FORZADO, el LLM literalmente
 * no puede responder texto: tiene que llamar la función.
 *
 * Esto convierte el bot de "agente libre con detector de alucinación"
 * a "agente guiado paso a paso, imposible de descarrilar".
 *
 * MÁQUINA DE ESTADOS:
 *   inicio → producto → entrega → identificacion → confirmacion → confirmado
 *
 * En cada paso, define:
 *   - tools_permitidas:   solo estas tools llegan al LLM (las demás se ocultan)
 *   - tool_choice:        'auto' (libre), 'required' (debe llamar UNA), o
 *                         ['type'=>'function','function'=>['name'=>'X']] (forzado)
 *   - instruccion:        system message corta y específica del paso
 */
class FlujoPedidoOrchestrator
{
    /**
     * Tools globales: siempre disponibles en cualquier paso.
     * Son las que NO son específicas del flujo de pedido (saludo, derivar, etc.)
     */
    private const TOOLS_GLOBALES = [
        'derivar_a_departamento',     // siempre debe poder derivar a humano
        'consultar_horarios',
        'consultar_promociones',
        'consultar_zonas_cobertura',
        // Cliente puede dar cédula en CUALQUIER momento del flujo. El bot
        // debe poder verificarla sin importar el paso actual.
        'verificar_cliente_erp',
        // Cliente puede pedir un producto en cualquier momento (incluso
        // en paso=confirmado tras un pedido anterior).
        'buscar_productos',
        // Cliente puede preguntar por sus pedidos previos en cualquier momento
        // ("¿cuántos pedidos tengo?", "estado de mi pedido", "ya llegó").
        'consultar_mis_pedidos',
        // Cliente puede adicionar productos a un pedido existente (dentro del
        // ANS configurado). La tool valida tiempo + crea pedido ligado + SGI.
        'crear_adicion_pedido',
    ];

    /**
     * Reglas por paso.
     */
    public function reglasParaPaso(string $paso): array
    {
        $reglas = [
            ConversacionPedidoEstado::PASO_INICIO => [
                'tools_permitidas' => [
                    'buscar_productos',
                    'productos_destacados',
                    'listar_categorias',
                ],
                'tool_choice' => 'auto',
                'instruccion' =>
                    "📍 PASO 0 — SALUDO\n"
                    . "Acción única en este paso: dar la BIENVENIDA y preguntar qué desea pedir.\n\n"
                    . "REGLAS:\n"
                    . "1. Si solo saluda ('hola/buenos días/etc') → saluda cordial y pregunta:\n"
                    . "   '¿En qué te ayudo? ¿Qué te provoca pedir hoy?'\n"
                    . "2. Si pregunta por HORARIOS / ZONAS / EMPRESA → responde con datos del prompt.\n"
                    . "3. Si menciona un PRODUCTO ('quiero pierna', 'tienen X') → llama "
                    . "`buscar_productos` con sus palabras LITERALES.\n\n"
                    . "PROHIBIDO en este paso:\n"
                    . "  ❌ Pedir cédula\n"
                    . "  ❌ Validar cobertura\n"
                    . "  ❌ Confirmar pedido\n"
                    . "  ❌ Pedir dirección o método de entrega",
            ],

            ConversacionPedidoEstado::PASO_PRODUCTO => [
                'tools_permitidas' => [
                    'buscar_productos',
                    'productos_destacados',
                    'listar_categorias',
                    'productos_de_categoria',
                    'info_producto',
                ],
                'tool_choice' => 'auto',
                'instruccion' =>
                    "🛒 PASO 1 — PRODUCTOS (qué quiere)\n"
                    . "Acción única en este paso: dejar CLAROS los productos y cantidades.\n\n"
                    . "REGLAS:\n"
                    . "1. Si menciona un producto → SIEMPRE llama `buscar_productos` antes de responder.\n"
                    . "2. Si encuentras el producto, muestra opciones con precio y unidad.\n"
                    . "3. Cuando el cliente confirme cantidad ('2 libras de X', 'dame ese mismo') → "
                    . "el sistema captura los productos al estado.\n"
                    . "4. Cuando ya hay producto + cantidad clara, pasa al PASO 2 preguntando:\n"
                    . "   '¿Prefieres DESPACHO o pasarás a RECOGER en sede?'\n\n"
                    . "PROHIBIDO en este paso:\n"
                    . "  ❌ Pedir cédula (eso es PASO 3)\n"
                    . "  ❌ Pedir dirección (eso es PASO 2)\n"
                    . "  ❌ Validar cobertura (eso es PASO 2)\n"
                    . "  ❌ Confirmar pedido (eso es PASO 4)",
            ],

            ConversacionPedidoEstado::PASO_ENTREGA => [
                'tools_permitidas' => [
                    'validar_cobertura',
                    'consultar_zonas_cobertura',
                    'buscar_productos',
                ],
                'tool_choice' => 'auto',
                'instruccion' =>
                    "🚚 PASO 2 — MÉTODO DE ENTREGA (cómo lo recibe)\n"
                    . "Acción única: definir DESPACHO o CLIENTE RECOGE.\n\n"
                    . "Solo hay DOS métodos posibles:\n\n"
                    . "  🅰️ DESPACHO (a domicilio):\n"
                    . "     - Cliente dice 'despacho' / 'envío' / 'a domicilio' / 'me lo mandas'\n"
                    . "     - O da una dirección directa ('cra X #Y-Z')\n"
                    . "     - Acción: pídele la dirección si no la dio, luego llama "
                    . "       `validar_cobertura(direccion, barrio, ciudad)`\n"
                    . "     - Si cobertura OK → captura dirección + costo + tiempo, avanza\n"
                    . "     - Si NO cubierta → ofrece la opción RECOGER\n\n"
                    . "  🅱️ CLIENTE RECOGE (en sede):\n"
                    . "     - Cliente dice 'recoger' / 'paso por él' / 'yo voy' / 'yo recojo'\n"
                    . "     - Acción: lista las sedes activas (las tienes en el system prompt) "
                    . "       y deja que el cliente escoja por nombre o número.\n"
                    . "     - El sistema captura sede_id automáticamente.\n\n"
                    . "PROHIBIDO en este paso:\n"
                    . "  ❌ Pedir cédula (eso es PASO 3)\n"
                    . "  ❌ Confirmar pedido\n"
                    . "  ❌ Pedir email / teléfono\n"
                    . "  ❌ Inventar costos de envío",
            ],

            ConversacionPedidoEstado::PASO_IDENTIFICACION => [
                'tools_permitidas' => [
                    'verificar_cliente_erp',
                ],
                'tool_choice' => 'auto',
                'instruccion' =>
                    "🪪 PASO 3 — IDENTIFICACIÓN (quién es)\n"
                    . "Acción única: pedir CÉDULA y verificar en SGI.\n\n"
                    . "FLUJO EXACTO:\n"
                    . "1. Pide la cédula en UN solo mensaje breve:\n"
                    . "   'Para registrar tu pedido, ¿me das tu número de cédula?'\n"
                    . "2. Cuando te dé un número de 6-12 dígitos → llama INMEDIATAMENTE "
                    . "`verificar_cliente_erp(cedula)`.\n\n"
                    . "RESULTADOS POSIBLES:\n"
                    . "  ✅ existe=true → el cliente YA está en SGI. El sistema usa sus datos del ERP "
                    . "(nombre, dirección, teléfono). NO le pidas más nada — saltarás a confirmación.\n"
                    . "  ❌ existe=false → es cliente NUEVO. El sistema avanzará al PASO 3.5 (datos "
                    . "del cliente) y allí pedirás el resto.\n\n"
                    . "PROHIBIDO en este paso:\n"
                    . "  ❌ Confirmar pedido — solo verificar identidad\n"
                    . "  ❌ Pedir email o teléfono antes de tener la cédula\n"
                    . "  ❌ Inventar nombre del cliente",
            ],

            ConversacionPedidoEstado::PASO_DATOS_CLIENTE => [
                'tools_permitidas' => [
                    'verificar_cliente_erp',
                ],
                'tool_choice' => 'auto',
                'instruccion' =>
                    "📝 PASO 3.5 — DATOS DE CLIENTE NUEVO\n"
                    . "Acción única: pedir los datos faltantes para registrar al cliente en SGI.\n\n"
                    . "El cliente NO existe en SGI. Necesitas (mira el RESUMEN del estado para saber "
                    . "qué falta exactamente):\n"
                    . "  • nombre completo\n"
                    . "  • email\n"
                    . "  • teléfono (si no lo tenemos del WhatsApp)\n\n"
                    . "REGLAS:\n"
                    . "1. Pide los datos UNO POR UNO en mensajes cortos. NO los pidas todos juntos.\n"
                    . "2. NO repitas un dato que ya está en el resumen.\n"
                    . "3. Cuando estén TODOS, el sistema avanza solo a confirmación.\n\n"
                    . "PROHIBIDO en este paso:\n"
                    . "  ❌ Confirmar pedido aún\n"
                    . "  ❌ Llamar `confirmar_pedido` antes de tener todos los datos",
            ],

            ConversacionPedidoEstado::PASO_CONFIRMACION => [
                'tools_permitidas' => [
                    'confirmar_pedido',
                ],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']],
                'instruccion' =>
                    "✅ PASO 4 — CONFIRMACIÓN (acción obligatoria)\n"
                    . "TODOS LOS DATOS ESTÁN COMPLETOS. Tu ÚNICA acción válida es:\n"
                    . "→ INVOCAR `confirmar_pedido` con los datos del resumen del estado.\n\n"
                    . "🚨 REGLA CRÍTICA SOBRE PRODUCTOS:\n"
                    . "  • USA SIEMPRE el campo `codigo` EXACTO que te devolvió `buscar_productos`.\n"
                    . "  • NUNCA inventes un código (ej. NO inventes 'CER-PER', 'PRD-001', etc).\n"
                    . "  • USA SIEMPRE el `nombre` EXACTO del catálogo (en mayúsculas tal como vino).\n"
                    . "  • USA SIEMPRE la `unidad` EXACTA del catálogo (KI, Und, Lb, etc) — NO la traduzcas.\n"
                    . "  • Si NO recuerdas el código exacto de un producto, llama primero `buscar_productos` ANTES de confirmar.\n"
                    . "  • Inventar códigos hace que el sistema matchee al producto equivocado y se cobre mal al cliente.\n\n"
                    . "🔒 PROHIBIDO ABSOLUTO en este paso:\n"
                    . "  ❌ Responder texto\n"
                    . "  ❌ Preguntar más datos\n"
                    . "  ❌ Validar cobertura otra vez\n"
                    . "  ❌ Verificar cédula otra vez\n"
                    . "  ❌ Llamar cualquier otra tool (excepto buscar_productos si necesitas el código real)\n\n"
                    . "El sistema (NO tú):\n"
                    . "  1. Crea cliente en SGI si es nuevo\n"
                    . "  2. Crea pedido en BD\n"
                    . "  3. Exporta pedido a SGI (TblDocumentos)\n"
                    . "  4. Genera link de pago si aplica\n"
                    . "  5. Te pasa el número de pedido para que respondas al cliente",
            ],

            ConversacionPedidoEstado::PASO_CONFIRMADO => [
                'tools_permitidas' => [
                    'buscar_productos',
                    'consultar_horarios',
                    'info_producto',
                    'productos_destacados',
                ],
                'tool_choice' => 'auto',
                'instruccion' => "El pedido del cliente YA fue creado y registrado. "
                    . "Comportamiento según lo que diga el cliente:\n"
                    . "  • Si pregunta por su pedido / cuándo llega / estado → responde con "
                    . "    número y estado. NO llames confirmar_pedido.\n"
                    . "  • Si dice 'otro pedido', 'quiero más', 'agrégame X' o menciona un "
                    . "    producto nuevo → el sistema reseteará automáticamente el estado y "
                    . "    arrancarás un flujo limpio en el siguiente turno. Tu respuesta debe "
                    . "    ser: 'Claro {nombre}, ¿qué quieres pedir esta vez?' (sin volver a "
                    . "    pedir cédula porque ya la tenemos guardada).\n"
                    . "  • Si solo agradece o despide → responde cordial breve.\n"
                    . "PROHIBIDO llamar `confirmar_pedido` de nuevo en este paso.",
            ],

            ConversacionPedidoEstado::PASO_ABANDONADO => [
                'tools_permitidas' => [
                    'buscar_productos',
                    'productos_destacados',
                ],
                'tool_choice' => 'auto',
                'instruccion' => "El cliente había abandonado el flujo y ahora vuelve. "
                    . "Salúdalo y pregúntale qué desea pedir hoy, empezando desde cero.",
            ],
        ];

        return $reglas[$paso] ?? $reglas[ConversacionPedidoEstado::PASO_INICIO];
    }

    /**
     * Tools permitidas para este paso (incluye las globales).
     */
    public function toolsPermitidas(string $paso): array
    {
        $reglas = $this->reglasParaPaso($paso);
        return array_unique(array_merge($reglas['tools_permitidas'], self::TOOLS_GLOBALES));
    }

    /**
     * Filtra el array de definiciones de tools de OpenAI dejando solo
     * las permitidas en el paso actual.
     */
    public function filtrarTools(array $todasLasTools, string $paso): array
    {
        $permitidas = $this->toolsPermitidas($paso);

        return array_values(array_filter($todasLasTools, function ($tool) use ($permitidas) {
            $nombre = $tool['function']['name'] ?? null;
            return $nombre && in_array($nombre, $permitidas, true);
        }));
    }

    /**
     * tool_choice apropiado para este paso. @return string|array
     */
    public function toolChoice(string $paso)
    {
        return $this->reglasParaPaso($paso)['tool_choice'];
    }

    /**
     * Instrucción del paso para inyectar al system prompt.
     */
    public function instruccion(string $paso): string
    {
        return $this->reglasParaPaso($paso)['instruccion'];
    }

    /**
     * Construye el system message COMPLETO del paso actual: incluye instrucción
     * + lista de tools disponibles. Se inyecta al final del prompt para que
     * sea lo último que ve el LLM antes de responder.
     */
    public function systemMessageParaPaso(ConversacionWhatsapp $conv, ?array $todasLasToolsDef = null): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);
        $paso   = $estado->paso_actual;

        $instruccion = $this->instruccion($paso);
        $permitidas  = $this->toolsPermitidas($paso);

        // Construir lista enriquecida con descripciones (si nos las pasaron)
        $toolsDescritas = [];
        if (is_array($todasLasToolsDef)) {
            $defByName = [];
            foreach ($todasLasToolsDef as $t) {
                $name = $t['function']['name'] ?? null;
                if ($name) $defByName[$name] = $t['function'];
            }

            foreach ($permitidas as $nombre) {
                $def = $defByName[$nombre] ?? null;
                if ($def) {
                    $desc = trim((string) ($def['description'] ?? ''));
                    $desc = mb_substr($desc, 0, 180);
                    if (mb_strlen((string) ($def['description'] ?? '')) > 180) $desc .= '…';

                    $props = $def['parameters']['properties'] ?? [];
                    if (!is_array($props)) $props = (array) $props;
                    $required = $def['parameters']['required'] ?? [];
                    $paramsStr = '';
                    if (!empty($props)) {
                        $partes = [];
                        foreach ($props as $pname => $_pdef) {
                            $partes[] = in_array($pname, $required, true) ? "{$pname}*" : $pname;
                        }
                        $paramsStr = ' (' . implode(', ', $partes) . ')';
                    }

                    $toolsDescritas[] = "  • `{$nombre}`{$paramsStr}\n    → {$desc}";
                } else {
                    $toolsDescritas[] = "  • `{$nombre}`";
                }
            }
        }

        $contenido = "🎯 ORQUESTADOR DE FLUJO — PASO ACTUAL: `{$paso}`\n\n"
                   . $instruccion . "\n\n"
                   . "🛠️ TOOLS DISPONIBLES EN ESTE PASO:\n";

        if (!empty($toolsDescritas)) {
            $contenido .= implode("\n", $toolsDescritas) . "\n";
        } else {
            $contenido .= "  " . implode(', ', $permitidas) . "\n";
        }

        $contenido .= "\nReglas:\n"
                    . "• El sistema NO te pasa otras tools — están ocultas en este paso.\n"
                    . "• Los parámetros marcados con `*` son obligatorios.\n"
                    . "• El paso avanzará automáticamente cuando completes los datos requeridos.";

        // 🛡️ ANTI-ALUCINACIÓN: en pasos donde el LLM podría llamar
        // confirmar_pedido, le inyectamos los productos del estado con
        // sus códigos REALES de BD para que no invente.
        $productosEstado = $estado->productos ?? [];
        if (!empty($productosEstado) && in_array($paso, [
            ConversacionPedidoEstado::PASO_PRODUCTO,
            ConversacionPedidoEstado::PASO_ENTREGA,
            ConversacionPedidoEstado::PASO_IDENTIFICACION,
            ConversacionPedidoEstado::PASO_DATOS_CLIENTE,
            ConversacionPedidoEstado::PASO_CONFIRMACION,
        ], true)) {
            $lineas = [];
            foreach ($productosEstado as $pe) {
                $code = trim((string) ($pe['code'] ?? $pe['codigo'] ?? ''));
                $name = trim((string) ($pe['name'] ?? $pe['nombre'] ?? ''));
                $qty  = $pe['quantity'] ?? $pe['cantidad'] ?? null;
                $unit = $pe['unit'] ?? $pe['unidad'] ?? '';
                if ($code === '' && $name === '') continue;

                // Validamos que el code exista en BD; si no, lo blanqueamos
                // y dejamos que el resolver lo busque por nombre.
                $codeReal = '';
                if ($code !== '') {
                    $existe = \App\Models\Producto::where('codigo', $code)->exists();
                    if ($existe) {
                        $codeReal = $code;
                    }
                }
                // Si no había code o estaba inventado, intentamos resolverlo por nombre
                if ($codeReal === '' && $name !== '') {
                    $prod = \App\Models\Producto::where('nombre', $name)
                        ->orWhere('nombre', mb_strtoupper($name))
                        ->first();
                    if ($prod) $codeReal = (string) $prod->codigo;
                }

                $lineas[] = "  • code=`{$codeReal}` name=`{$name}` qty={$qty}" . ($unit ? " unit={$unit}" : '');
            }
            if (!empty($lineas)) {
                $contenido .= "\n\n📦 PRODUCTOS DEL CARRITO ACTUAL (usa estos códigos EXACTOS, NO inventes otros):\n"
                           . implode("\n", $lineas);
            }
        }

        return ['role' => 'system', 'content' => $contenido];
    }
}
