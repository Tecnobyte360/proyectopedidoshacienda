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
                'instruccion' => "PASO INICIO: el cliente acaba de empezar.\n"
                    . "  • Si pregunta por HORARIOS, ZONAS, TELÉFONO, INFO de la empresa → "
                    . "RESPONDE DIRECTO con los datos que tienes en el system prompt. "
                    . "NO llames tools — esos datos están literales arriba.\n"
                    . "  • Si menciona un PRODUCTO → llama `buscar_productos` con sus palabras.\n"
                    . "  • Si solo saluda → saluda de vuelta y pregunta qué desea.\n"
                    . "NO confirmes pedido — aún no hay nada que confirmar.",
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
                'instruccion' => "PASO PRODUCTO: el cliente está eligiendo qué pedir. Tu único "
                    . "objetivo es ayudarlo a definir productos y cantidades. Usa "
                    . "`buscar_productos` cuando mencione algo concreto. Cuando ya "
                    . "tenga producto + cantidad, pregunta cómo prefiere recibir el pedido: "
                    . "'¿prefieres que te lo despachemos o vas a recogerlo en una de nuestras sedes?'. "
                    . "Los DOS únicos métodos de entrega son: DESPACHO (entrega a domicilio) y "
                    . "CLIENTE RECOGE (en una de nuestras sedes). "
                    . "PROHIBIDO: validar cobertura, pedir cédula o confirmar pedido en este paso.",
            ],

            ConversacionPedidoEstado::PASO_ENTREGA => [
                'tools_permitidas' => [
                    'validar_cobertura',
                    'consultar_zonas_cobertura',
                    'buscar_productos', // por si quiere agregar más productos
                ],
                'tool_choice' => 'auto',
                'instruccion' => "PASO ENTREGA: el cliente ya tiene productos. Define cómo se "
                    . "los entregan. Los DOS únicos métodos disponibles son:\n"
                    . "  • DESPACHO (a domicilio): si dice 'despacho', 'envío', 'a domicilio', "
                    . "'me lo mandas', o da una dirección → llama `validar_cobertura` con esa "
                    . "dirección.\n"
                    . "  • CLIENTE RECOGE: si dice 'recoger', 'paso por él', 'yo voy', 'yo recojo' "
                    . "→ muéstrale las sedes disponibles (del system prompt) y deja que escoja "
                    . "una. El sistema captura el nombre o el número (1, 2) y resuelve sede_id.\n"
                    . "Cuando esté validada cobertura O escogida sede, el sistema avanzará "
                    . "automáticamente. PROHIBIDO: pedir cédula o confirmar pedido en este paso.",
            ],

            ConversacionPedidoEstado::PASO_IDENTIFICACION => [
                'tools_permitidas' => [
                    'verificar_cliente_erp',
                ],
                'tool_choice' => 'auto',
                'instruccion' => "PASO IDENTIFICACIÓN: ya hay producto y entrega. PIDE LA CÉDULA al "
                    . "cliente en UN solo mensaje breve y natural. Cuando te la dé, llama "
                    . "INMEDIATAMENTE `verificar_cliente_erp` con esa cédula.\n"
                    . "Posibles resultados de la tool:\n"
                    . "  • existe=true → el cliente YA está registrado en SGI. NO le pidas más "
                    . "datos personales — el sistema avanzará automáticamente a confirmación.\n"
                    . "  • existe=false → es cliente NUEVO. El sistema avanzará al paso "
                    . "datos_cliente_nuevo y allí pedirás el resto de datos (nombre, email, "
                    . "teléfono, dirección).\n"
                    . "PROHIBIDO: confirmar pedido en este paso. Solo verificar.",
            ],

            ConversacionPedidoEstado::PASO_DATOS_CLIENTE => [
                'tools_permitidas' => [
                    // verificar_cliente_erp por si el cliente da otra cédula
                    'verificar_cliente_erp',
                ],
                'tool_choice' => 'auto',
                'instruccion' => "PASO DATOS CLIENTE NUEVO: el cliente NO existe en SGI/ERP. "
                    . "Pídele los datos faltantes en mensajes cortos y naturales: nombre completo, "
                    . "email, teléfono (si aún no lo tienes), y dirección si aplica. Mira el "
                    . "resumen del estado del pedido para saber EXACTAMENTE qué falta — pide solo "
                    . "lo que no está. Una vez los tengas TODOS, el sistema avanzará a "
                    . "confirmación automáticamente y al confirmar el pedido el sistema creará el "
                    . "cliente en SGI antes de crear el pedido. PROHIBIDO confirmar pedido aún.",
            ],

            ConversacionPedidoEstado::PASO_CONFIRMACION => [
                // 🚨 SOLO confirmar_pedido. NADA MÁS.
                'tools_permitidas' => [
                    'confirmar_pedido',
                ],
                // 🔒 FORZADO: el LLM no puede responder texto. DEBE llamar la función.
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']],
                'instruccion' => "🚨 PASO CONFIRMACIÓN — DATOS COMPLETOS, ACCIÓN OBLIGATORIA. "
                    . "Tu ÚNICA acción válida es invocar `confirmar_pedido` con los datos "
                    . "estructurados que aparecen en el resumen del estado del pedido. "
                    . "PROHIBIDO responder texto. PROHIBIDO preguntar nada. "
                    . "PROHIBIDO validar nada. INVOCA LA FUNCIÓN AHORA con todos los datos.",
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

        return ['role' => 'system', 'content' => $contenido];
    }
}
