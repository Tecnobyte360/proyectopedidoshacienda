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
    public function decidir(ConversacionWhatsapp $conv, string $mensaje, string $primerNombre = ''): array
    {
        $estado = app(EstadoPedidoService::class)->obtener($conv);

        // Captador determinista: extrae datos del mensaje al estado
        try {
            app(EstadoPedidoService::class)->captarDelMensajeUsuario($conv, $mensaje);
            $estado->refresh();
        } catch (\Throwable $e) {
            Log::warning('Router: captador falló: ' . $e->getMessage());
        }

        $msgN = mb_strtolower(Str::ascii(trim($mensaje)));

        // ── 1) ESTADO COMPLETO + CLIENTE CONOCIDO → CIERRE DIRECTO ──────────
        if ($estado->estaCompleto() && !$estado->confirmado_at) {
            Log::info('🤖 Router: estado COMPLETO — cierre directo sin LLM', [
                'conv_id' => $conv->id,
            ]);
            return [
                'accion'    => 'cerrar_pedido',
                'orderData' => $estado->aOrderData(),
                'razon'     => 'estado_completo',
            ];
        }

        // ── 2) AFIRMACIÓN DE CONFIRMACIÓN tras pregunta del bot ─────────────
        // Si el último mensaje del bot pidió confirmar y el cliente afirma →
        // cerrar directo.
        if ($estado->paso_actual === ConversacionPedidoEstado::PASO_CONFIRMACION
            && $this->esAfirmacion($msgN)
            && $estado->estaCompleto()) {
            Log::info('🤖 Router: cliente afirmó confirmación', ['conv_id' => $conv->id]);
            return [
                'accion'    => 'cerrar_pedido',
                'orderData' => $estado->aOrderData(),
                'razon'     => 'confirmacion_afirmada',
            ];
        }

        // ── 3) FALTA UN SOLO DATO ESPECÍFICO → preguntar hardcoded ──────────
        $faltantes = $estado->camposFaltantes();
        $cuantosFaltan = count($faltantes);

        // Si el mensaje es saludo puro / agradecimiento → mejor LLM
        if ($this->esSaludoOSocial($msgN)) {
            return ['accion' => 'llm'];
        }

        // Si el mensaje es consulta abierta (pregunta sobre catálogo / horarios / zonas)
        // → mejor LLM con tools de consulta
        if ($this->esConsultaAbierta($msgN)) {
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
                "Listo{$nombre}. ¿Cómo te lo llevamos? 🛵\n\n"
                . "1️⃣ *Despacho* a tu dirección\n"
                . "2️⃣ *Recoges* en nuestra sede\n\n"
                . "Dime cuál prefieres.",

            str_contains($falta, 'dirección') =>
                "Pásame por favor la *dirección* completa para el despacho 📍\n"
                . "(Ejemplo: Cra 50 #63B-48, barrio Prado, Bello)",

            str_contains($falta, 'sede de recogida') =>
                "¿En qué *sede* la recoges? Te paso las opciones disponibles.",

            str_contains($falta, 'validar cobertura') =>
                "Permíteme validar la cobertura de esa dirección…",

            str_contains($falta, 'cédula') =>
                "Para registrar tu pedido necesito tu *número de cédula* (sin puntos) 📝",

            str_contains($falta, 'nombre completo') =>
                "Por favor pásame tu *nombre completo*.",

            default => null,
        };
    }
}
