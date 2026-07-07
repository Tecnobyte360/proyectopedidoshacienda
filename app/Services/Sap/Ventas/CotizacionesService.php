<?php

namespace App\Services\Sap\Ventas;

use App\Services\Sap\SapServiceLayerClient;
use Illuminate\Support\Carbon;

/**
 * VENTAS · 1.0 Gestión de Cotizaciones.
 *
 * Analiza las cotizaciones (abiertas o cerradas) e indica si se convirtieron
 * en pedidos de venta o si no se ganó la cotización (perdida).
 *
 * Fuente en SAP (Service Layer):
 *   - /b1s/v1/Quotations  → cotizaciones (OQUT)
 *   - /b1s/v1/Orders      → para detectar conversión (líneas con BaseType 23 =
 *     cotización, BaseEntry = DocEntry de la cotización).
 *
 * DocumentStatus: 'bost_Open' (abierta) | 'bost_Close' (cerrada).
 * Cancelled: 'tYES' | 'tNO'.
 *
 * Regla de negocio (a afinar contra la base real):
 *   - abierta                     → pendiente / en gestión
 *   - cerrada + tiene pedido base → GANADA (convertida en pedido)
 *   - cerrada + sin pedido base   → PERDIDA (o vencida sin convertir)
 *   - cancelada                   → anulada
 */
class CotizacionesService
{
    public function __construct(private SapServiceLayerClient $sap) {}

    /**
     * Punto de entrada usado por la herramienta de IA `analizar_cotizaciones`.
     *
     * @param array{dias?:int, estado?:string, card_code?:string, top?:int} $args
     */
    public function analizar(array $args = []): array
    {
        $dias      = min(max((int) ($args['dias'] ?? 30), 1), 365);
        $estado    = strtolower(trim((string) ($args['estado'] ?? 'todas'))); // abierta|cerrada|todas
        $cardCode  = trim((string) ($args['card_code'] ?? ''));
        $top       = min(max((int) ($args['top'] ?? 40), 1), 100);

        $desde   = Carbon::now()->subDays($dias)->toDateString();
        $filtros = ["DocDate ge '{$desde}'"];

        if ($estado === 'abierta')  $filtros[] = "DocumentStatus eq 'bost_Open'";
        if ($estado === 'cerrada')  $filtros[] = "DocumentStatus eq 'bost_Close'";
        if ($cardCode !== '')       $filtros[] = "CardCode eq '" . str_replace("'", "''", $cardCode) . "'";

        $select = 'DocEntry,DocNum,CardCode,CardName,DocDate,DocDueDate,DocTotal,DocumentStatus,Cancelled';
        $query  = 'Quotations?$select=' . $select
            . '&$filter=' . rawurlencode(implode(' and ', $filtros))
            . '&$orderby=DocDate desc'
            . '&$top=' . $top;

        $res = $this->sap->get($query);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'error' => $res['error'] ?? 'sap_error', 'detalle' => $res['detalle'] ?? null];
        }

        // ¿Hay cotizaciones cerradas (no anuladas)? Solo entonces vale la pena
        // traer el mapa de conversiones (cotización → pedido).
        $hayCerradas = collect($res['value'])->contains(
            fn ($q) => ($q['DocumentStatus'] ?? '') === 'bost_Close' && ($q['Cancelled'] ?? 'tNO') !== 'tYES'
        );
        $convertidas = $hayCerradas ? $this->mapaConversiones($desde) : [];

        $items   = [];
        $resumen = ['total' => 0, 'abiertas' => 0, 'ganadas' => 0, 'perdidas' => 0, 'anuladas' => 0,
                    'valor_total' => 0.0, 'valor_ganado' => 0.0];

        foreach ($res['value'] as $q) {
            $anulada  = ($q['Cancelled'] ?? 'tNO') === 'tYES';
            $abierta  = ($q['DocumentStatus'] ?? '') === 'bost_Open';
            $valor    = (float) ($q['DocTotal'] ?? 0);
            $docEntry = (int) ($q['DocEntry'] ?? 0);

            $clasificacion = 'anulada';
            $pedido        = null;

            if ($anulada) {
                $clasificacion = 'anulada';
            } elseif ($abierta) {
                $clasificacion = 'abierta';
            } else {
                // cerrada: ¿se convirtió en pedido? (según el mapa de conversiones)
                $pedido = $convertidas[$docEntry] ?? null;
                $clasificacion = $pedido ? 'ganada' : 'perdida';
            }

            $items[] = [
                'doc_entry'         => $q['DocEntry'] ?? null,
                'cotizacion'        => $q['DocNum'] ?? null,
                'cliente'           => $q['CardName'] ?? '',
                'card_code'         => $q['CardCode'] ?? '',
                'fecha'             => $q['DocDate'] ?? null,
                'fecha_vencimiento' => $q['DocDueDate'] ?? null,
                'valor'             => $valor,
                'estado'            => $clasificacion,
                'pedido_generado'   => $pedido,   // DocNum del pedido si se ganó
            ];

            $resumen['total']++;
            $resumen['valor_total'] += $valor;
            match ($clasificacion) {
                'abierta' => $resumen['abiertas']++,
                'ganada'  => [$resumen['ganadas']++, $resumen['valor_ganado'] += $valor],
                'perdida' => $resumen['perdidas']++,
                default   => $resumen['anuladas']++,
            };
        }

        return ['ok' => true, 'resumen' => $resumen, 'cotizaciones' => $items];
    }

    /**
     * Construye el mapa [ DocEntry de cotización => DocNum de pedido ] de las
     * cotizaciones que se convirtieron en Orden de Venta.
     *
     * El Service Layer de este cliente NO soporta el filtro OData any() sobre
     * DocumentLines (devuelve HTTP 400), así que en vez de preguntar cotización
     * por cotización, traemos las órdenes del período y armamos el índice
     * inverso en PHP: cada línea de orden con BaseType = 23 (cotización) apunta
     * con BaseEntry al DocEntry de la cotización de origen.
     */
    private function mapaConversiones(string $desde): array
    {
        $mapa = [];
        $skip = 0;
        $paginas = 0;

        do {
            $query = 'Orders?$select=DocNum,DocumentLines'
                . '&$filter=' . rawurlencode("DocDate ge '{$desde}'")
                . '&$orderby=DocEntry asc&$top=100&$skip=' . $skip;

            $res = $this->sap->get($query);
            if (!($res['ok'] ?? false)) {
                break;
            }

            $filas = $res['value'] ?? [];
            foreach ($filas as $ord) {
                foreach (($ord['DocumentLines'] ?? []) as $l) {
                    if ((int) ($l['BaseType'] ?? -1) === 23) {
                        $baseEntry = $l['BaseEntry'] ?? null;
                        if ($baseEntry !== null && $baseEntry !== '') {
                            $mapa[(int) $baseEntry] = (string) ($ord['DocNum'] ?? '');
                        }
                    }
                }
            }

            $skip += 100;
            $paginas++;
        } while (count($filas) === 100 && $paginas < 10); // tope de seguridad: 1000 órdenes

        return $mapa;
    }
}
