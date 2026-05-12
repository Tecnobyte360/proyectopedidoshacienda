<?php

namespace App\Services\Bots;

use App\Models\AnsPedido;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Services\BotCatalogoService;
use App\Services\IntegracionExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🛒 ADICIÓN DE PEDIDOS
 *
 * Crea un pedido NUEVO ligado a un pedido origen vía `pedido_origen_id`.
 * Copia tenant, cliente, sede, dirección, lat/lng del origen y agrega los
 * productos pedidos. Valida que el ANS "adicionar" no haya expirado.
 * Exporta a SGI como documento separado (preserva integridad contable).
 *
 *   $r = app(AdicionPedidoService::class)->crear(
 *       pedidoOrigenId: 95,
 *       productos: [['code' => 'POSTA', 'name' => 'Posta', 'qty' => 1, 'unit' => 'Kl']],
 *       from: '573216499744',
 *   );
 *   // ['ok' => true, 'pedido_id' => 96, 'total' => 18000, 'errores' => []]
 */
class AdicionPedidoService
{
    /**
     * @param int    $pedidoOrigenId  Pedido al que se adicionan productos
     * @param array  $productos       [['code'=>?, 'name'=>?, 'qty'=>?, 'unit'=>?], ...]
     * @param string $from            Teléfono del cliente que pide (para auditoría)
     * @return array{ok: bool, pedido_id: ?int, total: float, errores: string[]}
     */
    public function crear(int $pedidoOrigenId, array $productos, string $from): array
    {
        $errores = [];

        $origen = Pedido::with('sede', 'detalles')->find($pedidoOrigenId);
        if (!$origen) {
            return ['ok' => false, 'pedido_id' => null, 'total' => 0, 'errores' => ["Pedido #{$pedidoOrigenId} no encontrado"]];
        }

        // 1) Validar ANS de adicionar (5 min por defecto, según configuración del tenant)
        $ansMin = $this->ansMinutosAdicionar($origen->tenant_id);
        if ($ansMin !== null) {
            $transcurridos = (int) round($origen->fecha_pedido->diffInSeconds(now()) / 60);
            if ($transcurridos > $ansMin) {
                return [
                    'ok'        => false,
                    'pedido_id' => null,
                    'total'     => 0,
                    'errores'   => ["Ya pasaron {$transcurridos} min desde el pedido #{$pedidoOrigenId} (ANS adicionar: {$ansMin} min)"],
                ];
            }
        }

        // 2) Validar estado del pedido — no se adiciona si ya está entregado o cancelado
        if (in_array($origen->estado, [Pedido::ESTADO_ENTREGADO ?? 'entregado', Pedido::ESTADO_CANCELADO ?? 'cancelado'], true)) {
            return [
                'ok'        => false,
                'pedido_id' => null,
                'total'     => 0,
                'errores'   => ["No se puede adicionar al pedido #{$pedidoOrigenId} porque su estado es '{$origen->estado}'"],
            ];
        }

        if (empty($productos)) {
            return ['ok' => false, 'pedido_id' => null, 'total' => 0, 'errores' => ['Sin productos para adicionar']];
        }

        // 3) Resolver productos contra catálogo (precio/código reales)
        $catalogo = app(BotCatalogoService::class);
        $lineas = [];
        $total  = 0.0;
        foreach ($productos as $p) {
            $clave = trim((string) ($p['code'] ?? $p['name'] ?? ''));
            if ($clave === '') {
                $errores[] = "Producto sin nombre ni código";
                continue;
            }
            $cat = $catalogo->resolverProducto($clave, $origen->sede_id);
            if (!$cat) {
                $errores[] = "Producto '{$clave}' no encontrado en catálogo";
                continue;
            }
            $qty   = (float) ($p['qty'] ?? $p['quantity'] ?? 1);
            $price = (float) ($cat->precio_base ?? 0);
            if ($qty <= 0 || $price <= 0) {
                $errores[] = "Cantidad/precio inválido para '{$cat->nombre}' (qty={$qty}, price={$price})";
                continue;
            }
            $subtotal = $price * $qty;
            $total   += $subtotal;
            $lineas[] = [
                'producto_id'      => $cat->id,
                'codigo'           => $cat->codigo,
                'producto'         => $cat->nombre,
                'cantidad'         => $qty,
                'unidad'           => $p['unit'] ?? $cat->unidad,
                'precio_unitario'  => $price,
                'subtotal'         => $subtotal,
            ];
        }

        if (empty($lineas)) {
            return ['ok' => false, 'pedido_id' => null, 'total' => 0, 'errores' => $errores ?: ['No se pudo resolver ningún producto']];
        }

        // 4) Crear pedido adición copiando datos clave del origen
        DB::beginTransaction();
        try {
            $adicion = Pedido::create([
                'tenant_id'         => $origen->tenant_id,
                'pedido_origen_id'  => $origen->id,
                'sede_id'           => $origen->sede_id,
                'empresa_id'        => $origen->empresa_id,
                'cliente_id'        => $origen->cliente_id ?? null,
                'fecha_pedido'      => now(),
                'estado'            => Pedido::ESTADO_NUEVO ?? 'nuevo',
                'fecha_estado'      => now(),
                'observacion_estado'=> "Adición al pedido #{$origen->id}",
                'total'             => $total,
                'notas'             => "🛒 ADICIÓN al pedido #{$origen->id}",
                'cliente_nombre'    => $origen->cliente_nombre,
                'direccion'         => $origen->direccion,
                'barrio'            => $origen->barrio,
                'lat'               => $origen->lat,
                'lng'               => $origen->lng,
                'zona_cobertura_id' => $origen->zona_cobertura_id,
                'telefono_whatsapp' => $origen->telefono_whatsapp,
                'telefono_contacto' => $origen->telefono_contacto,
                'telefono'          => $origen->telefono,
                'canal'             => 'whatsapp',
                'connection_id'     => $origen->connection_id,
                'whatsapp_id'       => $origen->whatsapp_id,
            ]);

            foreach ($lineas as $linea) {
                DetallePedido::create(array_merge(['pedido_id' => $adicion->id], $linea));
            }

            DB::commit();

            Log::info('🛒 ADICIÓN creada', [
                'adicion_id'        => $adicion->id,
                'pedido_origen_id'  => $origen->id,
                'total'             => $total,
                'lineas'            => count($lineas),
                'tenant_id'         => $origen->tenant_id,
                'from'              => $from,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('❌ Error creando adición', [
                'pedido_origen_id' => $origen->id,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'pedido_id' => null, 'total' => 0, 'errores' => ['Error interno al guardar la adición']];
        }

        // 5) Exportar a SGI (documento separado, referenciando al origen en notas)
        try {
            $res = app(IntegracionExportService::class)->exportarPedido($adicion);
            Log::info('🛒 ADICIÓN exportada a SGI', ['adicion_id' => $adicion->id, 'resultado' => $res]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ ADICIÓN no se pudo exportar a SGI (se conserva en BD local)', [
                'adicion_id' => $adicion->id,
                'error'      => $e->getMessage(),
            ]);
            // No es un error fatal: el pedido queda en local y se puede re-exportar.
        }

        return [
            'ok'        => true,
            'pedido_id' => $adicion->id,
            'total'     => $total,
            'errores'   => $errores, // productos parcialmente fallidos (info, no bloquea)
        ];
    }

    private function ansMinutosAdicionar(int $tenantId): ?int
    {
        $ans = AnsPedido::where('tenant_id', $tenantId)
            ->where('activo', true)
            ->where('accion', 'adicionar')
            ->first();
        return $ans ? (int) $ans->tiempo_minutos : null;
    }
}
