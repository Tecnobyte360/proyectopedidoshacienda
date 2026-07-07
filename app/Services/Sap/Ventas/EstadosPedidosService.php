<?php

namespace App\Services\Sap\Ventas;

use App\Services\Sap\SapServiceLayerClient;
use Illuminate\Support\Carbon;

/**
 * VENTAS · 1.1 Análisis de Estados de Pedidos.
 *
 * Analiza las órdenes de venta ABIERTAS: si están despachadas, facturadas,
 * valida la fecha de vencimiento y detecta cantidades retrasadas por despachar
 * o facturar.
 *
 * Fuentes en SAP (Service Layer):
 *   - /b1s/v1/Orders            → cabecera + líneas (cantidades abiertas)
 *   - /b1s/v1/sml.svc/ESTADOPEDIDO → vista semántica (despacho/producción),
 *     ya usada por el COTIZADOR (BuscarEstadoPedido).
 *
 * NOTA: los nombres de campos siguen el estándar de SAP B1 SL. Se ajustarán
 * contra la base real cuando tengamos conectividad desde un equipo autorizado.
 */
class EstadosPedidosService
{
    public function __construct(private SapServiceLayerClient $sap) {}

    /**
     * Punto de entrada usado por la herramienta de IA `analizar_estados_pedidos`.
     *
     * @param array{card_code?:string, solo_vencidos?:bool, top?:int} $args
     */
    public function analizar(array $args = []): array
    {
        $cardCode     = trim((string) ($args['card_code'] ?? ''));
        $soloVencidos = (bool) ($args['solo_vencidos'] ?? false);
        $top          = min(max((int) ($args['top'] ?? 30), 1), 100);

        $filtros = ["DocumentStatus eq 'bost_Open'"];
        if ($cardCode !== '') {
            $filtros[] = "CardCode eq '" . str_replace("'", "''", $cardCode) . "'";
        }

        $select = 'DocEntry,DocNum,CardCode,CardName,DocDate,DocDueDate,DocumentStatus,DocTotal,DocumentLines';
        $query  = 'Orders?$select=' . $select
            . '&$filter=' . rawurlencode(implode(' and ', $filtros))
            . '&$orderby=DocDueDate asc'
            . '&$top=' . $top;

        $res = $this->sap->get($query);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'error' => $res['error'] ?? 'sap_error', 'detalle' => $res['detalle'] ?? null];
        }

        $hoy       = Carbon::now()->startOfDay();
        $pedidos   = [];
        $resumen   = ['total' => 0, 'vencidos' => 0, 'con_pendiente' => 0, 'valor_abierto' => 0.0];

        foreach ($res['value'] as $ord) {
            $vence      = !empty($ord['DocDueDate']) ? Carbon::parse($ord['DocDueDate'])->startOfDay() : null;
            $vencido    = $vence ? $vence->lt($hoy) : false;
            $diasVenc   = $vence ? $hoy->diffInDays($vence, false) : null; // negativo = vencido

            $cantPedida = 0.0;
            $cantPend   = 0.0;
            $lineas     = [];
            foreach (($ord['DocumentLines'] ?? []) as $l) {
                $q    = (float) ($l['Quantity'] ?? 0);
                $open = (float) ($l['RemainingOpenQuantity'] ?? 0);
                $cantPedida += $q;
                $cantPend   += $open;
                $lineas[] = [
                    'item'        => $l['ItemCode'] ?? '',
                    'descripcion' => $l['ItemDescription'] ?? '',
                    'cantidad'    => $q,
                    'pendiente'   => $open,          // por despachar
                    'estado'      => $l['LineStatus'] ?? '',
                ];
            }

            $tienePend = $cantPend > 0;
            if ($soloVencidos && !$vencido) {
                continue;
            }

            $pedidos[] = [
                'doc_entry'         => $ord['DocEntry'] ?? null,
                'pedido'            => $ord['DocNum'] ?? null,
                'cliente'           => $ord['CardName'] ?? '',
                'card_code'         => $ord['CardCode'] ?? '',
                'fecha'             => $ord['DocDate'] ?? null,
                'fecha_vencimiento' => $ord['DocDueDate'] ?? null,
                'vencido'           => $vencido,
                'dias_para_vencer'  => $diasVenc,
                'valor'             => (float) ($ord['DocTotal'] ?? 0),
                'cant_pedida'       => $cantPedida,
                'cant_pendiente'    => $cantPend,      // retrasada por despachar
                'lineas'            => $lineas,
            ];

            $resumen['total']++;
            $resumen['valor_abierto'] += (float) ($ord['DocTotal'] ?? 0);
            if ($vencido)   $resumen['vencidos']++;
            if ($tienePend) $resumen['con_pendiente']++;
        }

        return [
            'ok'      => true,
            'resumen' => $resumen,
            'pedidos' => $pedidos,
        ];
    }

    /**
     * Detalle de estado (despacho/producción) de un pedido puntual, usando la
     * vista semántica ESTADOPEDIDO (misma que usa el COTIZADOR).
     */
    public function estadoDetalle(int $docEntry): array
    {
        $query = 'sml.svc/ESTADOPEDIDO?$filter=' . rawurlencode("DocEntry eq {$docEntry}");
        $res   = $this->sap->get($query);

        return [
            'ok'    => (bool) ($res['ok'] ?? false),
            'filas' => $res['value'] ?? [],
        ];
    }
}
