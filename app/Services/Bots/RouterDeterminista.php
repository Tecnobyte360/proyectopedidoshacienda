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
        if (!empty($estado->direccion)
            && $estado->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO
            && !$estado->cobertura_validada) {
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

        // ── 1) AFIRMACIÓN EXPLÍCITA DE CONFIRMACIÓN → CIERRE DIRECTO ───────
        // SOLO cerrar el pedido si el cliente afirma explícitamente Y el
        // último mensaje del bot pidió confirmación o mostró resumen.
        if ($estado->estaCompleto() && !$estado->confirmado_at && $this->esAfirmacion($msgN)) {
            $ultBotPidioConfirmar = $this->ultimoMensajeBotPidioConfirmar($conv);
            if ($ultBotPidioConfirmar) {
                Log::info('🤖 Router: cliente afirmó confirmación tras resumen del bot', [
                    'conv_id' => $conv->id,
                ]);
                return [
                    'accion'    => 'cerrar_pedido',
                    'orderData' => $estado->aOrderData(),
                    'razon'     => 'confirmacion_afirmada',
                ];
            }
        }

        // ── 2) ESTADO COMPLETO → PEDIR CONFIRMACIÓN (mostrar resumen) ───────
        // Si todos los datos están listos pero el cliente NO ha confirmado,
        // mostrar resumen y esperar confirmación.
        if ($estado->estaCompleto() && !$estado->confirmado_at && !$this->esAfirmacion($msgN)) {
            $ultBotPidioConfirmar = $this->ultimoMensajeBotPidioConfirmar($conv);
            if (!$ultBotPidioConfirmar) {
                $resumen = $this->construirResumenParaConfirmar($estado, $primerNombre);
                Log::info('🤖 Router: estado completo — pidiendo confirmación al cliente', [
                    'conv_id' => $conv->id,
                ]);
                return ['accion' => 'reply', 'reply' => $resumen];
            }
        }

        // ── 3) FALTA UN SOLO DATO ESPECÍFICO → preguntar hardcoded ──────────
        $faltantes = $estado->camposFaltantes();
        $cuantosFaltan = count($faltantes);

        // Si el mensaje es saludo puro / agradecimiento → mejor LLM
        if ($this->esSaludoOSocial($msgN)) {
            return ['accion' => 'llm'];
        }

        // Si el mensaje es una consulta CLARAMENTE off-topic (no relacionada
        // con el negocio: clima, política, fútbol, recetas, etc.) → respuesta
        // segura sin LLM. Evita que el bot se ponga a hablar de cualquier cosa.
        if ($this->esOffTopic($msgN)) {
            Log::info('🛡️ Router: pregunta off-topic detectada — respuesta segura', [
                'conv_id' => $conv->id,
                'msg' => mb_substr($mensaje, 0, 100),
            ]);
            $nombreParte = $primerNombre ? " {$primerNombre}" : '';
            return [
                'accion' => 'reply',
                'reply'  => "Hola{$nombreParte}, soy el asistente de pedidos. "
                          . "Puedo ayudarte con:\n"
                          . "• Catálogo y precios\n"
                          . "• Domicilios y zonas de cobertura\n"
                          . "• Horarios\n"
                          . "• Tomar tu pedido\n\n"
                          . "¿En qué te ayudo hoy?",
            ];
        }

        // Si el mensaje es consulta abierta (pregunta sobre catálogo / horarios / zonas)
        // → mejor LLM con tools de consulta
        if ($this->esConsultaAbierta($msgN)) {
            return ['accion' => 'llm'];
        }

        // 🛡️ Caso especial: si solo falta "validar cobertura" Y ya tenemos
        // dirección, pasar al LLM para que invoque validar_cobertura. NO
        // responder con "permíteme validar..." sin acción real.
        if ($cuantosFaltan === 1 && str_contains($faltantes[0], 'cobertura') && !empty($estado->direccion)) {
            Log::info('🤖 Router: cobertura por validar, delegando al LLM con tools', [
                'conv_id' => $conv->id,
                'direccion' => $estado->direccion,
            ]);
            return ['accion' => 'llm'];
        }

        // Si solo falta UN dato y el contexto lo amerita → preguntarlo
        if ($cuantosFaltan === 1 && $estado->productos) {
            $falta = $faltantes[0];
            $reply = $this->preguntaPorFaltante($falta, $primerNombre, $estado);
            if ($reply) {
                Log::info('🤖 Router: preguntando dato faltante', [
                    'conv_id' => $conv->id,
                    'falta'   => $falta,
                ]);
                return ['accion' => 'reply', 'reply' => $reply];
            }
        }

        // ── 4) Sin decisión clara → LLM ─────────────────────────────────────
        return ['accion' => 'llm'];
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

    private function esSaludoOSocial(string $msg): bool
    {
        $saludos = [
            'hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches',
            'gracias', 'muchas gracias', 'mil gracias', 'chao', 'adios', 'bye',
            'hasta luego', 'que mas', 'qué más', 'que tal', 'cómo estas',
        ];
        foreach ($saludos as $s) {
            if ($msg === $s) return true;
        }
        return false;
    }

    /**
     * Detecta preguntas claramente fuera del scope del negocio:
     * clima, política, deportes, recetas, chistes, vida personal, etc.
     */
    private function esOffTopic(string $msg): bool
    {
        $patrones = [
            '/\b(clima|temperatura|llueve|nublado|sol|llover)\b/u',
            '/\b(politic|elecci|presidente|gobierno|congreso)\b/u',
            '/\b(futbol|f[uú]tbol|partido|gol|nacional|millonarios|america|junior|mundial)\b/u',
            '/\b(receta|cocinar|cocinero|chef|preparar|preparacion)\b/u',
            '/\b(chiste|chistoso|gracioso|broma)\b/u',
            '/\b(c[oó]mo\s+est[áa]s|qu[eé]\s+haces|d[oó]nde\s+vives|c[oó]mo\s+te\s+llamas)\b/u',
            '/\b(novia|novio|esposa|esposo|hijos|familia)\b/u',
            '/\b(eres\s+humano|eres\s+robot|eres\s+(una\s+)?ia|inteligencia\s+artificial|chatgpt|openai)\b/u',
            '/\b(dolar|d[oó]lar|euro|bitcoin|criptomoneda)\b/u',
            '/\b(remedios?\s+caseros?|salud|enfermedad|medicina|doctor)\b/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $msg)) return true;
        }
        return false;
    }

    private function esConsultaAbierta(string $msg): bool
    {
        // Preguntas sobre info (no captura un dato concreto)
        $patrones = [
            '/\b(que|qu[ée])\s+tienen|tienes\b/u',
            '/\b(que|qu[ée])\s+(hay|venden|manejan)\b/u',
            '/\b(horario|horarios|abren|cerrad)\b/u',
            '/\b(zona|zonas|cobertura|cubren)\b/u',
            '/\bpromocion|promociones|descuento|combo\b/u',
            '/\bcatalog|productos\b/u',
            '/\bcuanto\s+(vale|cuesta|sale)\b/u',
            '/\bvalor|precio\b/u',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $msg)) return true;
        }
        return false;
    }

    private function preguntaPorFaltante(string $falta, string $primerNombre, ConversacionPedidoEstado $estado): ?string
    {
        $nombre = $primerNombre ? " {$primerNombre}" : '';

        return match (true) {
            str_contains($falta, 'método de entrega') =>
                "Perfecto{$nombre}. ¿Cómo prefieres recibir tu pedido?\n\n"
                . "1. *Despacho* a tu dirección\n"
                . "2. *Recoges* en sede\n\n"
                . "Indícame la opción.",

            str_contains($falta, 'dirección') =>
                "Por favor compárteme la *dirección completa* para el despacho.\n"
                . "Ejemplo: _Cra 50 #63B-48, barrio Prado, Bello_",

            str_contains($falta, 'sede de recogida') =>
                "¿En qué *sede* prefieres recoger tu pedido? Te confirmo las opciones disponibles.",

            str_contains($falta, 'validar cobertura') =>
                "Permíteme validar la cobertura de esa dirección.",

            str_contains($falta, 'cédula') =>
                "Para registrar tu pedido necesito tu *número de cédula* (sin puntos).",

            str_contains($falta, 'nombre completo') =>
                "Por favor pásame tu *nombre completo*.",

            default => null,
        };
    }
}
