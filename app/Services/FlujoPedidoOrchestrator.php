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
                    . "tenga producto + cantidad, pregunta '¿es para entrega a domicilio "
                    . "o quieres recogerlo en una de nuestras sedes?'. "
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
                    . "los entregan:\n"
                    . "  • Si dice 'a domicilio' o da una dirección → llama `validar_cobertura`.\n"
                    . "  • Si dice 'recoger' / 'paso por él' → muéstrale las sedes disponibles "
                    . "(tomadas del contexto de empresa que tienes en el system prompt) y deja "
                    . "que escoja una. Una vez escoja, el sistema captura el nombre y resuelve "
                    . "el sede_id automáticamente.\n"
                    . "Cuando esté validada cobertura O escogida sede, el sistema avanzará "
                    . "automáticamente. PROHIBIDO: pedir cédula o confirmar pedido en este paso.",
            ],

            ConversacionPedidoEstado::PASO_IDENTIFICACION => [
                'tools_permitidas' => [
                    'verificar_cliente_erp',
                ],
                'tool_choice' => 'auto',
                'instruccion' => "PASO IDENTIFICACIÓN: ya hay producto y entrega. Pide la cédula "
                    . "al cliente en UN solo mensaje breve. Cuando te la dé, llama "
                    . "INMEDIATAMENTE `verificar_cliente_erp` con esa cédula. "
                    . "Si el ERP devuelve datos del cliente, NO le pidas más datos personales — "
                    . "el sistema avanzará al siguiente paso. "
                    . "Si no existe en ERP, pídele su nombre completo. "
                    . "PROHIBIDO: confirmar pedido en este paso. Solo verificar.",
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
    public function systemMessageParaPaso(ConversacionWhatsapp $conv): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);
        $paso   = $estado->paso_actual;

        $instruccion = $this->instruccion($paso);
        $permitidas  = $this->toolsPermitidas($paso);

        $contenido = "🎯 ORQUESTADOR DE FLUJO — PASO ACTUAL: `{$paso}`\n\n"
                   . $instruccion . "\n\n"
                   . "🛠 TOOLS DISPONIBLES EN ESTE PASO: " . implode(', ', $permitidas) . ".\n"
                   . "El sistema NO pasa al LLM otras tools — están ocultas en este paso. "
                   . "El paso avanzará automáticamente cuando completes los datos requeridos.";

        return ['role' => 'system', 'content' => $contenido];
    }
}
