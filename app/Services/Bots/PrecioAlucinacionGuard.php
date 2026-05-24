<?php

namespace App\Services\Bots;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use Illuminate\Support\Facades\Log;

/**
 * 🛡️ Post-validator del reply del bot.
 *
 * Detecta cuando el LLM "alucina" precios en un resumen (ej. dice
 * "5 unidades × $7.000 = $35.000" cuando el precio real es $2.500/u).
 *
 * Si encuentra discrepancias en precios o totales, REEMPLAZA el bloque
 * del resumen con un render exacto generado por código desde el estado
 * persistente del pedido. Asi el cliente NUNCA ve precios inflados.
 *
 * El cobro real (Pedido en BD) siempre fue correcto gracias al cortafuego
 * que valida productos antes de guardar. Esto solo arregla el TEXTO visible.
 */
class PrecioAlucinacionGuard
{
    /** Tolerancia: discrepancia mayor a este % considera alucinación */
    private const TOLERANCIA_PCT = 3.0;

    public function validarYCorregir(string $reply, ConversacionWhatsapp $conv): string
    {
        // Solo procesar si el reply parece un resumen con precios
        if (!$this->parecResumen($reply)) {
            return $reply;
        }

        $estado = ConversacionPedidoEstado::query()
            ->where('conversacion_id', $conv->id)
            ->first();

        if (!$estado || empty($estado->productos) || !is_array($estado->productos)) {
            return $reply; // sin estado no podemos validar
        }

        // Calcular total esperado del estado
        $subtotalEsperado = 0;
        $itemsEsperados = [];
        foreach ($estado->productos as $p) {
            $cant   = (float) ($p['cantidad'] ?? $p['quantity'] ?? 0);
            $precio = (float) ($p['precio_unitario'] ?? $p['unit_price'] ?? $p['precio'] ?? 0);
            $subtot = $cant * $precio;
            $subtotalEsperado += $subtot;
            $itemsEsperados[] = [
                'nombre'   => (string) ($p['nombre'] ?? $p['name'] ?? '—'),
                'cantidad' => $cant,
                'unidad'   => (string) ($p['unidad'] ?? $p['unit'] ?? 'und'),
                'precio'   => $precio,
                'subtotal' => $subtot,
            ];
        }

        if ($subtotalEsperado <= 0) return $reply;

        // Extraer "totales" mencionados en el reply (números con $)
        $totalesEnReply = $this->extraerTotales($reply);

        // Si NO menciona ningún total en absoluto, no podemos validar
        if (empty($totalesEnReply)) return $reply;

        // Detectar discrepancia: si alguno de los "$N" en el reply es muy
        // diferente al subtotal esperado y a sus subtotales unitarios
        $alucinacionDetectada = $this->detectarDiscrepancia(
            $totalesEnReply,
            $subtotalEsperado,
            $itemsEsperados
        );

        if (!$alucinacionDetectada) {
            return $reply;
        }

        Log::warning('🚨 ALUCINACIÓN DE PRECIOS detectada en reply del bot', [
            'conv_id'            => $conv->id,
            'subtotal_esperado'  => $subtotalEsperado,
            'totales_en_reply'   => $totalesEnReply,
            'productos_estado'   => $itemsEsperados,
            'reply_preview'      => mb_substr($reply, 0, 200),
        ]);

        return $this->reemplazarConRenderExacto($reply, $itemsEsperados, $subtotalEsperado, $estado);
    }

    /**
     * Heurística: el reply parece un resumen si contiene marcadores típicos
     * (subtotal, total, productos con $) y al menos un nombre de producto + $.
     */
    private function parecResumen(string $reply): bool
    {
        $lower = mb_strtolower($reply);
        $tieneDolar = str_contains($reply, '$');
        $tieneTotal = str_contains($lower, 'total') || str_contains($lower, 'subtotal') || str_contains($lower, 'resumo');
        return $tieneDolar && $tieneTotal;
    }

    /** Extrae todos los importes "$N.NNN" o "$N.NNN.NNN" del reply. */
    private function extraerTotales(string $reply): array
    {
        preg_match_all('/\$\s*([\d\.\,]+)/', $reply, $m);
        $totales = [];
        foreach ($m[1] ?? [] as $n) {
            $valor = (float) str_replace(['.', ' '], ['', ''], str_replace(',', '.', $n));
            if ($valor > 0 && $valor < 100_000_000) {
                $totales[] = $valor;
            }
        }
        return $totales;
    }

    /**
     * Devuelve true si NINGUNO de los $N del reply coincide con el subtotal
     * esperado ni con los subtotales unitarios (cant×precio). Esto significa
     * que el LLM se inventó precios.
     */
    private function detectarDiscrepancia(array $totalesEnReply, float $subtotalEsperado, array $items): bool
    {
        $valoresValidos = [$subtotalEsperado];
        foreach ($items as $it) {
            $valoresValidos[] = $it['subtotal'];
            $valoresValidos[] = $it['precio'];
        }

        // Para cada total mencionado en el reply, ver si AL MENOS UNO matchea
        // un valor válido dentro de la tolerancia.
        $matches = 0;
        foreach ($totalesEnReply as $t) {
            foreach ($valoresValidos as $v) {
                if ($v == 0) continue;
                $diff = abs($t - $v) / $v * 100;
                if ($diff <= self::TOLERANCIA_PCT) {
                    $matches++;
                    break;
                }
            }
        }

        // Si NINGUNO de los $ del reply coincide → alucinación clara.
        // Si solo algunos coinciden y otros no → también es sospechoso.
        $totalReply = count($totalesEnReply);
        $tasaMatch = $totalReply > 0 ? ($matches / $totalReply) : 0;

        return $tasaMatch < 0.5; // menos del 50% match → alucinación
    }

    /**
     * Reemplaza el cuerpo del resumen del LLM con render exacto desde estado.
     * Conserva el saludo inicial y la pregunta final del LLM si es posible.
     */
    private function reemplazarConRenderExacto(
        string $replyOriginal,
        array $items,
        float $subtotal,
        ConversacionPedidoEstado $estado
    ): string {
        $envio = (float) ($estado->costo_envio ?? 0);
        $total = $subtotal + $envio;

        $lineas = [];
        $lineas[] = '🛒 *Resumen de tu pedido:*';
        $lineas[] = '';
        foreach ($items as $it) {
            $cantTxt = $it['cantidad'] == floor($it['cantidad'])
                ? (int) $it['cantidad']
                : rtrim(rtrim(number_format($it['cantidad'], 2, ',', '.'), '0'), ',');
            $lineas[] = sprintf(
                '• %s %s × *%s* = $%s',
                $cantTxt,
                $it['unidad'],
                $it['nombre'],
                number_format($it['subtotal'], 0, ',', '.')
            );
        }
        $lineas[] = '';
        $lineas[] = '💰 *Subtotal:* $' . number_format($subtotal, 0, ',', '.');
        if ($envio > 0) {
            $lineas[] = '🚚 *Envío:* $' . number_format($envio, 0, ',', '.');
        }
        $lineas[] = '💳 *Total a pagar:* $' . number_format($total, 0, ',', '.');
        $lineas[] = '';
        $lineas[] = '¿*Confirmas*? Responde *si* o *pásame los cambios*.';
        $lineas[] = '';
        $lineas[] = '_(Resumen recalculado por el sistema para asegurar precios correctos.)_';

        return implode("\n", $lineas);
    }
}
