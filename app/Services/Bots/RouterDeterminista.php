<?php

namespace App\Services\Bots;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Services\EstadoPedidoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🤖 ROUTER DETERMINISTA
 *
 * Decide la siguiente acción del bot SIN llamar al LLM cuando es posible.
 * Reduce el espacio de error: el LLM solo se invoca cuando hay genuina
 * ambigüedad (consultas abiertas, info, saludos).
 *
 * Reglas (orden de evaluación):
 *   1. Estado completo + cliente en SGI/conocido → CIERRE DIRECTO (sin LLM).
 *   2. Falta UN solo dato específico (productos, método, dirección, cédula)
 *      → respuesta hardcoded preguntando ese dato.
 *   3. Cliente afirma confirmación tras pregunta → cierre directo.
 *   4. Mensaje del cliente es saludo / consulta de info → fallthrough al LLM.
 */
class RouterDeterminista
{
    /**
     * Evalúa el estado y decide. Devuelve:
     *   ['accion' => 'reply', 'reply' => '...']         → enviar texto
     *   ['accion' => 'cerrar_pedido', 'orderData' => …] → invocar guardarPedidoDesdeToolCall
     *   ['accion' => 'llm']                              → dejar pasar al LLM
     */
    public function decidir(ConversacionWhatsapp $conv, string $mensaje, string $primerNombre = '', ?int $connectionId = null): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);

        // Captador determinista: extrae datos del mensaje al estado
        try {
            app(EstadoPedidoService::class)->captarDelMensajeUsuario($conv, $mensaje);
            $estado->refresh();
        } catch (\Throwable $e) {
            Log::warning('Router: captador falló: ' . $e->getMessage());
        }

        // 🗺️ AGENTE DE COBERTURA: si tenemos dirección de despacho y la
        // cobertura no se ha validado, ejecutar validación AHORA (sin LLM).
        //
        // 🛡️ GATE: solo correr si la dirección está vinculada al MENSAJE ACTUAL
        // (cliente acaba de darla o de mencionar dirección/domicilio). Si solo
        // saludó, no tiene sentido re-validar una dirección heredada de un
        // pedido anterior — el cliente puede estar empezando algo nuevo.
        if (!empty($estado->direccion)
            && $estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO
            && !$estado->cobertura_validada
            && $this->mensajeJustificaValidarCobertura($mensaje)) {
            try {
                $cob = app(\App\Services\Bots\AgenteCoberturaService::class)
                    ->evaluar($conv, $connectionId);

                if ($cob['accion'] === 'fuera_de_cobertura' && !empty($cob['reply'])) {
                    return ['accion' => 'reply', 'reply' => $cob['reply']];
                }
                // Si quedó cubierta o no aplica → continúa flujo (refresh estado)
                $estado = $estado->fresh() ?: $estado;
            } catch (\Throwable $e) {
                Log::warning('Router: agente cobertura falló: ' . $e->getMessage());
            }
        }

        $msgN = mb_strtolower(Str::ascii(trim($mensaje)));

        // ── 1) AFIRMACIÓN EXPLÍCITA + RESUMEN PREVIO → CIERRE DIRECTO ───────
        if ($estado->estaCompleto() && !$estado->confirmado_at && $this->esAfirmacion($msgN)) {
            if ($this->ultimoMensajeBotPidioConfirmar($conv)) {
                Log::info('🤖 Router: cliente afirmó confirmación tras resumen', ['conv_id' => $conv->id]);
                return [
                    'accion'    => 'cerrar_pedido',
                    'orderData' => $estado->aOrderData(),
                    'razon'     => 'confirmacion_afirmada',
                ];
            }
        }

        // ── 2) ESTADO COMPLETO Y BOT NO HA MOSTRADO RESUMEN → mostrarlo ─────
        if ($estado->estaCompleto() && !$estado->confirmado_at && !$this->esAfirmacion($msgN)) {
            if (!$this->ultimoMensajeBotPidioConfirmar($conv)) {
                Log::info('🤖 Router: estado completo — mostrando resumen', ['conv_id' => $conv->id]);
                return ['accion' => 'reply', 'reply' => $this->construirResumenParaConfirmar($estado, $primerNombre)];
            }
        }

        // ── 3) TODO LO DEMÁS → LLM ──────────────────────────────────────────
        // Sin hardcoded responses para faltantes / off-topic. El LLM con el
        // system prompt y las tools sabe qué hacer mejor que un match rígido.
        return ['accion' => 'llm'];
    }

    /**
     * 🛡️ ¿El mensaje actual justifica re-validar la cobertura?
     *
     * Razón: si el cliente solo saluda ("hola", "buenos días") pero el estado
     * heredó una dirección + método=domicilio de un pedido anterior, NO
     * queremos re-disparar la validación de cobertura y mandarle "fuera de
     * zona" cuando no ha pedido nada. Solo validamos cuando el cliente:
     *
     *   - Acaba de dar/mencionar una dirección o barrio
     *   - Pide explícitamente domicilio/despacho/envío
     *   - Pregunta por cobertura
     *   - Responde a una pregunta del bot sobre dirección/cobertura
     */
    private function mensajeJustificaValidarCobertura(string $mensaje): bool
    {
        $m = mb_strtolower(Str::ascii(trim($mensaje)));
        if ($m === '') return false;

        // Saludos puros / despedidas / agradecimientos → NO re-validar
        $saludos = [
            'hola', 'holaa', 'holaaa', 'hi', 'hey',
            'buenas', 'buenos dias', 'buenas tardes', 'buenas noches',
            'buen dia', 'saludos', 'que tal', 'que mas',
            'gracias', 'muchas gracias', 'mil gracias', 'ok', 'listo',
            'chao', 'adios', 'bye', 'hasta luego',
        ];
        if (in_array($m, $saludos, true)) return false;
        if (preg_match('/^\s*(hola|holaa+|buenas|buen[oa]s\s+(dias|tardes|noches)|buen\s+dia)\s*[\.!?]*\s*$/i', $m)) {
            return false;
        }

        // Patrones que SÍ justifican validar cobertura
        $patrones = [
            // Domicilio / despacho explícito
            '/\b(domicilio|despacho|env[ií]o|env[ií]a|env[ií]ar|mand[áa]r|para\s+casa|a\s+mi\s+casa|me\s+lo\s+mand|m[áa]ndamelo|me\s+lo\s+env)\b/iu',
            // Mención de dirección / barrio / ciudad
            '/\b(direcci[oó]n|barrio|calle|carrera|cra|cl|cll|avenida|av\.?|diagonal|transversal|manzana|mz|apartament|apto|casa|edificio)\b/iu',
            // Patrón típico de dirección colombiana: "CL 50 # 30-15", "carrera 50 # 30-15"
            '/\b(cl|cll|calle|cra|carrera|kr|av|avenida|dg|diagonal|tv|transversal)\s*\d+/iu',
            // Pregunta de cobertura
            '/\b(cobertura|llegan|llegas|llevas|llevan|cubren|cubre|zona|reparten|reparto)\b/iu',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $m)) return true;
        }
        return false;
    }

    /**
     * Determina si el último mensaje del bot pidió confirmación explícita.
     * Busca frases como "¿confirmas?", "te confirmo", "¿procedo?", etc.
     */
    private function ultimoMensajeBotPidioConfirmar(ConversacionWhatsapp $conv): bool
    {
        $ult = \App\Models\MensajeWhatsapp::query()
            ->where('conversacion_id', $conv->id)
            ->where('rol', 'assistant')
            ->orderByDesc('id')
            ->limit(1)
            ->value('contenido');

        if (!$ult) return false;
        $u = mb_strtolower(Str::ascii($ult));

        $patrones = [
            'confirmas', 'confirma el pedido', 'confirmas el pedido',
            'procedo', 'procedo con', 'lo registro',
            'todo bien', 'esta bien', 'queda asi', 'queda así',
            'asi queda', 'así queda', 'te lo dejo asi', 'te lo dejo así',
            'esta correcto', 'está correcto',
            '¿confirmas', '¿procedo', '¿asi', '¿así',
            'resumen del pedido', 'asi quedaria', 'así quedaría',
            'queda esto', 'esto queda',
        ];
        foreach ($patrones as $p) {
            if (str_contains($u, $p)) return true;
        }
        return false;
    }

    /**
     * Construye un resumen del pedido para mostrar al cliente antes de
     * pedirle confirmación.
     */
    private function construirResumenParaConfirmar(ConversacionPedidoEstado $estado, string $primerNombre): string
    {
        $nombre = $primerNombre ? " {$primerNombre}" : '';

        $lineas = ["Listo{$nombre}, te resumo tu pedido:\n"];

        // Productos
        $total = 0.0;
        if (!empty($estado->productos)) {
            foreach ($estado->productos as $p) {
                $name = $p['name'] ?? '?';
                $qty  = $p['quantity'] ?? 1;
                $unit = $p['unit'] ?? 'und';
                // Obtener precio real del catálogo
                try {
                    $prod = app(\App\Services\BotCatalogoService::class)
                        ->resolverProducto($p['code'] ?? $name, $estado->sede_id);
                    $precio = $prod ? (float) ($prod->precio_base ?? 0) : 0;
                    $sub = $precio * $qty;
                    $total += $sub;
                    $lineas[] = sprintf("• %s × %s %s — \$%s",
                        $qty, $unit, $name, number_format($sub, 0, ',', '.'));
                } catch (\Throwable $e) {
                    $lineas[] = "• {$qty} {$unit} {$name}";
                }
            }
        }

        // Método de entrega
        if ($estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO) {
            $lineas[] = "\n📍 *Despacho a:* {$estado->direccion}";
            if (!empty($estado->distancia_km)) {
                $lineas[] = "   (Cobertura validada ✅, sede a {$estado->distancia_km} km)";
            }
        } elseif ($estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_RECOGER) {
            $sedeName = $estado->sede?->nombre ?? 'Sede';
            $lineas[] = "\n🏪 *Recoges en:* {$sedeName}";
        }

        // Cliente
        if (!empty($estado->nombre_cliente)) {
            $lineas[] = "\n👤 *Cliente:* {$estado->nombre_cliente}";
        }
        if (!empty($estado->cedula)) {
            $lineas[] = "🪪 *Cédula:* {$estado->cedula}";
        }

        // Total
        if ($total > 0) {
            $lineas[] = "\n💰 *Total:* \$" . number_format($total, 0, ',', '.');
        }

        $lineas[] = "\n¿*Confirmas* el pedido? Responde *sí* o pásame los cambios.";

        return implode("\n", $lineas);
    }

    private function esAfirmacion(string $msg): bool
    {
        $afirmaciones = [
            'si', 'sí', 'si confirmo', 'confirmo', 'confirmado', 'dale', 'listo',
            'ok', 'okay', 'perfecto', 'claro', 'bueno', 'esta bien', 'está bien',
            'vamos', 'va', 'va pues', 'hagamoslo', 'asi es', 'así es',
        ];
        foreach ($afirmaciones as $a) {
            if ($msg === $a || str_contains($msg, $a)) return true;
        }
        return false;
    }

}
