<?php

namespace App\Services\Bots;

use App\Models\ConversacionWhatsapp;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🛡️ ORDER VALIDATOR AGENT
 *
 * Corre ANTES de que el bot muestre el resumen al cliente o intente
 * cerrar el pedido. Su única misión: detectar problemas TEMPRANO para
 * evitar el flujo feo de:
 *
 *   bot: "muestra resumen" → cliente: "Si" → guard: "falta email" →
 *   bot: "dame email" → cliente: "x@y" → bot: "otro resumen DIFERENTE"
 *
 * Responsabilidades:
 *   1. CAPA DETERMINISTA: validar campos obligatorios usando la misma
 *      regla del cortafuego de confirmar_pedido (reutilizada vía
 *      WhatsappWebhookController::validarOrderDataPublic).
 *
 *   2. DETECCIÓN DE OBJECIONES: si el cliente confirma con frases tipo
 *      "Si pero yo no me llamo X" → marca objection_detected y sugiere
 *      qué dato corregir.
 *
 *   3. CHEQUEO DE CONSISTENCIA: alerta si la dirección está pero el
 *      envío no aparece en el cálculo (caso del bug del envío perdido).
 *
 * NO usa LLM en esta primera versión — toda la lógica es determinista
 * y barata (~5ms por llamada). Phase 2 podría agregar un análisis con
 * Haiku 4.5 para detectar objeciones más sutiles.
 */
class OrderValidatorAgent
{
    /**
     * Resultado de validar:
     *
     * [
     *   'ready_to_summarize' => bool,   // ¿se puede mostrar resumen?
     *   'ready_to_close'     => bool,   // ¿se puede invocar confirmar_pedido?
     *   'missing_fields'     => array,  // ej ['correo electrónico', 'cédula']
     *   'objection_detected' => bool,   // cliente reclamó algo en el mensaje actual
     *   'objection_reason'   => string, // qué reclamó
     *   'consistency_alert'  => string|null, // alerta de estado inconsistente
     *   'suggested_reply'    => string|null, // mensaje sugerido para el bot
     * ]
     */
    public function validar(ConversacionWhatsapp $conv, string $mensajeUsuario = ''): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);
        $orderData = $estado->aOrderData();

        // ── 1) VALIDACIÓN DETERMINISTA ───────────────────────────────────
        $faltantes = [];
        try {
            $faltantes = app(\App\Http\Controllers\WhatsappWebhookController::class)
                ->validarOrderDataPublic($orderData);
        } catch (\Throwable $e) {
            Log::warning('OrderValidator: validación determinista falló: ' . $e->getMessage());
        }

        // ── 2) DETECCIÓN DE OBJECIONES EN EL MENSAJE ACTUAL ──────────────
        $objection = $this->detectarObjecion($mensajeUsuario, $orderData);

        // ── 3) CHEQUEO DE CONSISTENCIA DE ESTADO ─────────────────────────
        $consistencyAlert = $this->detectarInconsistencias($estado, $orderData);

        // ── 4) DECISIÓN ──────────────────────────────────────────────────
        $readyToSummarize = empty($faltantes);
        $readyToClose     = $readyToSummarize && !$objection['detected'];

        $suggestedReply = $this->construirRespuestaSugerida(
            $faltantes,
            $objection,
            $consistencyAlert
        );

        Log::info('🛡️ OrderValidatorAgent', [
            'conv_id'           => $conv->id,
            'missing'           => $faltantes,
            'objection'         => $objection['detected'],
            'consistency_alert' => $consistencyAlert,
            'ready_summarize'   => $readyToSummarize,
            'ready_close'       => $readyToClose,
        ]);

        return [
            'ready_to_summarize' => $readyToSummarize,
            'ready_to_close'     => $readyToClose,
            'missing_fields'     => $faltantes,
            'objection_detected' => $objection['detected'],
            'objection_reason'   => $objection['razon'],
            'consistency_alert'  => $consistencyAlert,
            'suggested_reply'    => $suggestedReply,
        ];
    }

    /**
     * Detecta si el cliente, además de afirmar, también reclama o corrige
     * un dato. Casos típicos:
     *   "Si pero yo no me llamo test"
     *   "Si pero el envío está mal"
     *   "Confirmo pero la dirección es la otra"
     *   "Dale pero quita el camarón"
     */
    private function detectarObjecion(string $mensaje, array $orderData): array
    {
        $m = mb_strtolower(Str::ascii(trim($mensaje)));
        if ($m === '') return ['detected' => false, 'razon' => ''];

        // Conectores típicos de objeción: "pero", "aunque", "sin embargo"
        $tieneConector = preg_match('/\b(pero|aunque|sin embargo|solo que|nomas que)\b/u', $m) === 1;
        if (!$tieneConector) return ['detected' => false, 'razon' => ''];

        // Mapeo de qué dato puede estar reclamando
        $patrones = [
            'nombre'    => '/\b(no me llamo|mi nombre es|no soy|me llamo|el nombre)\b/u',
            'cedula'    => '/\b(mi cedula es|cedula equivocada|no es esa cedula|cedula correcta)\b/u',
            'direccion' => '/\b(la direccion|direccion equivocada|no es esa direccion|mi direccion es|cambia la direccion)\b/u',
            'producto'  => '/\b(quita|cambia el producto|no quiero ese|no es ese producto|saca|remueve)\b/u',
            'envio'     => '/\b(envio|domicilio|despacho).*?(mal|equivocado|no es|raro|distinto)\b/u',
            'total'     => '/\b(total|precio|valor).*?(mal|equivocado|no cuadra|raro|distinto)\b/u',
            'metodo'    => '/\b(pago|efectivo|tarjeta|transferencia).*?(no|cambia|otro)\b/u',
        ];

        foreach ($patrones as $dato => $regex) {
            if (preg_match($regex, $m)) {
                return ['detected' => true, 'razon' => $dato];
            }
        }

        // Conector presente pero no matcheamos patrón específico → objeción genérica
        return ['detected' => true, 'razon' => 'generica'];
    }

    /**
     * Detecta inconsistencias entre los campos del estado.
     * Caso típico: dirección capturada + método=domicilio + cobertura validada
     * pero el subtotal_envio es 0 → el resumen va a salir sin envío.
     */
    private function detectarInconsistencias($estado, array $orderData): ?string
    {
        // Caso 1: domicilio con cobertura validada pero sin costo de envío
        if (!empty($estado->direccion)
            && $estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO
            && $estado->cobertura_validada
            && (int)($estado->envio_costo ?? 0) <= 0) {
            return 'Hay dirección y cobertura validada pero el envío cuesta $0 — revisar zona';
        }

        // Caso 2: productos vacíos pero el estado se marcó completo
        if (empty($orderData['products'] ?? [])
            && method_exists($estado, 'estaCompleto')
            && $estado->estaCompleto()) {
            return 'Estado marcado completo pero NO hay productos en el carrito';
        }

        // Caso 3: cliente recogiendo pero con dirección capturada (suele ser un error)
        if ($estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_RECOGER
            && !empty($estado->direccion)) {
            return 'Cliente eligió RECOGER pero hay una dirección capturada (puede confundir)';
        }

        return null;
    }

    /**
     * Construye un mensaje natural para que el bot responda al cliente
     * en vez de mostrar el resumen prematuro.
     */
    private function construirRespuestaSugerida(array $faltantes, array $objection, ?string $consistencyAlert): ?string
    {
        // Prioridad 1: objeción del cliente (resolver primero)
        if ($objection['detected']) {
            return match ($objection['razon']) {
                'nombre'    => '¡Perdón! ¿Me confirmas entonces tu nombre completo, por favor?',
                'cedula'    => 'Claro, ¿me confirmas el número correcto de cédula?',
                'direccion' => 'Por supuesto, ¿cuál es la dirección correcta?',
                'producto'  => 'Listo, dime qué quieres cambiar del pedido y lo ajusto.',
                'envio'     => 'Déjame revisar el envío de nuevo. ¿Me confirmas la dirección exacta?',
                'total'     => 'Déjame recalcular el total. ¿Me confirmas los productos y cantidad otra vez?',
                'metodo'    => '¿Qué método de pago prefieres entonces?',
                default     => 'Cuéntame qué hay que ajustar y lo corrijo.',
            };
        }

        // Prioridad 2: campos faltantes
        if (!empty($faltantes)) {
            $lista = implode(', ', $faltantes);
            return "Antes de mostrarte el resumen me falta saber: *{$lista}*. ¿Me lo compartes?";
        }

        // Prioridad 3: inconsistencia (solo log interno, no se le dice al cliente)
        return null;
    }
}
