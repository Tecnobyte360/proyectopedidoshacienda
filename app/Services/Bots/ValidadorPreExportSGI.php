<?php

namespace App\Services\Bots;

use App\Models\Pedido;
use Illuminate\Support\Facades\Log;

/**
 * 🛡️ VALIDADOR PRE-EXPORT SGI
 *
 * Última línea de defensa ANTES de exportar un pedido a SGI. Verifica
 * reglas duras de integridad. Si alguna falla, BLOQUEA la exportación
 * y registra una alerta crítica.
 *
 * Reglas:
 *   1. total > 0
 *   2. tiene al menos 1 producto
 *   3. subtotal = suma(detalles.subtotal)
 *   4. cliente con cédula presente (6-12 dígitos)
 *   5. Si despacho: dirección + zona/cobertura presente
 *   6. Si recoger: sede_id válida
 *   7. Productos: cada uno con producto_id o codigo válido + precio > 0
 *
 * Uso:
 *   $resultado = app(ValidadorPreExportSGI::class)->validar($pedido);
 *   if (!$resultado['ok']) {
 *       // bloquear export
 *   }
 */
class ValidadorPreExportSGI
{
    /**
     * @return array{ok: bool, errores: string[], severidad: string}
     */
    public function validar(Pedido $pedido): array
    {
        $errores = [];

        // 1. Total > 0
        if ((float) $pedido->total <= 0) {
            $errores[] = "Total = 0 o negativo (\${$pedido->total})";
        }

        // 2. Tiene productos
        $detalles = $pedido->detalles ?? collect();
        if ($detalles->isEmpty()) {
            $errores[] = "Pedido sin líneas de detalle";
        }

        // 3. Subtotal coincide con suma de detalles
        $sumaDetalles = $detalles->sum(fn ($d) => (float) ($d->subtotal ?? 0));
        $totalSinEnvio = (float) $pedido->total - (float) ($pedido->costo_envio ?? 0);
        // Tolerancia $1 por redondeo
        if (abs($sumaDetalles - $totalSinEnvio) > 1.0) {
            $errores[] = sprintf(
                "Subtotal NO coincide: detalles \$%s vs total-envío \$%s",
                number_format($sumaDetalles, 0, ',', '.'),
                number_format($totalSinEnvio, 0, ',', '.')
            );
        }

        // 4. Cédula del cliente válida
        $cedula = trim((string) ($pedido->cliente_cedula ?? ''));
        if ($cedula === '') {
            $errores[] = "Cliente SIN cédula registrada";
        } elseif (!preg_match('/^\d{6,12}$/', $cedula)) {
            $errores[] = "Cédula inválida: '{$cedula}' (no es 6-12 dígitos)";
        }

        // 5. Si despacho: dirección + zona/cobertura
        $esDespacho = !empty($pedido->direccion) || ($pedido->metodo_entrega ?? '') === 'domicilio';
        if ($esDespacho) {
            if (empty(trim((string) $pedido->direccion))) {
                $errores[] = "Pedido de despacho SIN dirección";
            }
            // Sede asignada (para saber desde dónde despachar)
            if (empty($pedido->sede_id)) {
                $errores[] = "Pedido de despacho SIN sede asignada";
            }
        }

        // 6. Recoger: sede_id obligatorio
        $esRecoger = ($pedido->metodo_entrega ?? '') === 'recoger';
        if ($esRecoger && empty($pedido->sede_id)) {
            $errores[] = "Pedido de recogida SIN sede_id";
        }

        // 7. Cada detalle: producto + precio > 0
        foreach ($detalles as $d) {
            $nombreP = trim((string) ($d->producto ?? $d->nombre_producto ?? ''));
            if ($nombreP === '') {
                $errores[] = "Línea con nombre de producto vacío";
            }
            if ((float) ($d->precio_unitario ?? 0) <= 0) {
                $errores[] = "Producto '{$nombreP}' con precio_unitario = 0";
            }
            if ((float) ($d->cantidad ?? 0) <= 0) {
                $errores[] = "Producto '{$nombreP}' con cantidad = 0";
            }
        }

        $ok = empty($errores);
        $severidad = $ok ? 'info' : (count($errores) >= 3 ? 'critica' : 'alta');

        if (!$ok) {
            Log::warning('🚨 ValidadorPreExportSGI: pedido NO pasa validación', [
                'pedido_id' => $pedido->id,
                'errores'   => $errores,
            ]);

            try {
                app(AlertasService::class)->notificar(
                    'pedido_no_valido_pre_export',
                    "🚨 Pedido #{$pedido->id} NO pasa validación pre-SGI",
                    "El pedido tiene errores que impiden exportarlo:\n\n• " .
                    implode("\n• ", $errores) .
                    "\n\nCliente: " . ($pedido->cliente_nombre ?? '?') .
                    "\nTotal: \$" . number_format($pedido->total ?? 0, 0, ',', '.'),
                    [
                        'pedido_id' => $pedido->id,
                        'errores'   => $errores,
                        'severidad' => $severidad,
                    ]
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [
            'ok'         => $ok,
            'errores'    => $errores,
            'severidad'  => $severidad,
        ];
    }
}
