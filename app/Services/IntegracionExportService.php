<?php

namespace App\Services;

use App\Models\Integracion;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Exporta pedidos al ERP del cliente cuando se confirman.
 *
 * Soporta:
 *  - INSERT en tabla de encabezados (TblDocumentos)
 *  - INSERT por cada línea de detalle (TblDetalleDocumentos)
 *
 * Sistema de variables: cualquier valor en config puede usar placeholders:
 *   {pedido.id}, {pedido.total}, {pedido.fecha}, {pedido.fecha_hora}
 *   {cliente.cedula}, {cliente.nombre}, {cliente.telefono}
 *   {consecutivo}            ← IntDocumento calculado (MAX+1)
 *   {detalle.codigo}, {detalle.cantidad}, {detalle.unidad}
 *   {detalle.precio}, {detalle.subtotal}
 *   {ano}, {mes}, {dia}      ← componentes de fecha actual
 */
class IntegracionExportService
{
    public function __construct(
        private IntegracionSyncService $sync,
    ) {}

    /**
     * Exporta el pedido a TODAS las integraciones del tenant que tengan
     * `exporta_pedidos = true`. Si falla una, sigue con las otras.
     * Cada export se loguea en `integracion_export_logs`.
     */
    public function exportarPedido(Pedido $pedido): array
    {
        $integraciones = Integracion::where('tenant_id', $pedido->tenant_id)
            ->where('activo', true)
            ->where('exporta_pedidos', true)
            ->get();

        if ($integraciones->isEmpty()) {
            return ['exportadas' => 0, 'resultados' => []];
        }

        $resultados = [];

        foreach ($integraciones as $integracion) {
            try {
                $r = $this->exportarA($integracion, $pedido);
                $resultados[$integracion->id] = $r;
            } catch (\Throwable $e) {
                Log::error('❌ Export pedido falló', [
                    'integracion_id' => $integracion->id,
                    'pedido_id'      => $pedido->id,
                    'error'          => $e->getMessage(),
                ]);
                DB::table('integracion_export_logs')->insert([
                    'tenant_id'      => $pedido->tenant_id,
                    'integracion_id' => $integracion->id,
                    'pedido_id'      => $pedido->id,
                    'estado'         => 'error',
                    'error_mensaje'  => $e->getMessage(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $resultados[$integracion->id] = ['estado' => 'error', 'mensaje' => $e->getMessage()];
            }
        }

        return [
            'exportadas' => count($resultados),
            'resultados' => $resultados,
        ];
    }

    /**
     * Inserta el pedido en la integración específica.
     * Construye el SQL y lo ejecuta en la BD del cliente.
     */
    private function exportarA(Integracion $integracion, Pedido $pedido): array
    {
        $cfg = $integracion->config['export'] ?? [];
        $tablaHeader = $cfg['tabla'] ?? 'TblDocumentos';

        $pdo = $this->sync->conectar($integracion);
        $documentoId = $this->siguienteConsecutivo($pdo, $tablaHeader, $cfg);

        // Contexto base de variables (header + cada línea de detalle)
        $ctxBase = $this->construirContexto($pedido, $documentoId);

        // ── 1. INSERT en tabla de ENCABEZADOS ─────────────────────────────
        $headerFields = $this->camposHeader($cfg, $ctxBase);
        [$sqlHeader, $paramsHeader] = $this->construirInsert($tablaHeader, $headerFields);

        $stmt = $pdo->prepare($sqlHeader);
        $stmt->execute($paramsHeader);

        // ── 2. INSERT por cada LÍNEA de DETALLE ──────────────────────────
        $detalleInsertados = 0;
        $tablaDetalle = trim((string) ($cfg['detalle']['tabla'] ?? ''));
        $exportarDetalle = ($cfg['detalle']['activo'] ?? false) && $tablaDetalle !== '';

        if ($exportarDetalle && $pedido->detalles && $pedido->detalles->count() > 0) {
            foreach ($pedido->detalles as $linea) {
                $ctxLinea = $ctxBase + $this->construirContextoDetalle($linea);
                $detFields = $this->camposDetalle($cfg, $ctxLinea);
                [$sqlDet, $paramsDet] = $this->construirInsert($tablaDetalle, $detFields);
                $stmtDet = $pdo->prepare($sqlDet);
                $stmtDet->execute($paramsDet);
                $detalleInsertados++;
            }
        }

        // Log
        $sqlPreview = $this->sqlConValores($sqlHeader, $paramsHeader);
        DB::table('integracion_export_logs')->insert([
            'tenant_id'      => $pedido->tenant_id,
            'integracion_id' => $integracion->id,
            'pedido_id'      => $pedido->id,
            'estado'         => 'ok',
            'documento_id'   => $documentoId,
            'sql_ejecutado'  => $sqlPreview . ($detalleInsertados > 0 ? "\n\n[+ {$detalleInsertados} línea(s) en {$tablaDetalle}]" : ''),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Log::info('✅ Pedido exportado al ERP', [
            'pedido_id'         => $pedido->id,
            'integracion_id'    => $integracion->id,
            'tabla_header'      => $tablaHeader,
            'tabla_detalle'     => $tablaDetalle,
            'documento_id'      => $documentoId,
            'lineas_detalle'    => $detalleInsertados,
        ]);

        return [
            'estado'       => 'ok',
            'documento_id' => $documentoId,
            'tabla'        => $tablaHeader,
            'lineas'       => $detalleInsertados,
        ];
    }

    /**
     * Construye contexto de variables del pedido para interpolación.
     */
    private function construirContexto(Pedido $pedido, int $documentoId): array
    {
        $hoy = $pedido->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $hoyHora = $pedido->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');
        $total = (float) $pedido->total;

        return [
            'pedido.id'         => $pedido->id,
            'pedido.total'      => $total,
            'pedido.subtotal'   => $total,
            'pedido.neto'       => $total,
            'pedido.fecha'      => $hoy,
            'pedido.fecha_hora' => $hoyHora,
            'pedido.barrio'     => (string) ($pedido->barrio ?? ''),
            'pedido.direccion'  => (string) ($pedido->direccion ?? ''),
            'cliente.cedula'    => $this->resolverTercero($pedido),
            'cliente.nombre'    => (string) ($pedido->cliente_nombre ?? ''),
            'cliente.telefono'  => (string) ($pedido->telefono_whatsapp ?? $pedido->telefono ?? ''),
            'consecutivo'       => $documentoId,
            'ano'               => date('Y'),
            'mes'               => date('n'),
            'dia'               => date('j'),
        ];
    }

    private function construirContextoDetalle($detalle): array
    {
        return [
            'detalle.codigo'   => (string) ($detalle->codigo_producto ?? ''),
            'detalle.nombre'   => (string) ($detalle->producto ?? ''),
            'detalle.cantidad' => (float) ($detalle->cantidad ?? 1),
            'detalle.unidad'   => (string) ($detalle->unidad ?? 'Und'),
            'detalle.precio'   => (float) ($detalle->precio_unitario ?? 0),
            'detalle.subtotal' => (float) ($detalle->subtotal ?? 0),
        ];
    }

    /**
     * Mapeo del header → array [nombreColumna => valorResuelto].
     * Cada valor del cfg puede ser literal o usar {variable}.
     */
    private function camposHeader(array $cfg, array $ctx): array
    {
        return [
            'IntEmpresa'        => $this->resolver($cfg['empresa']     ?? 1, $ctx),
            'IntTransaccion'    => $this->resolver($cfg['transaccion'] ?? '009', $ctx),
            'IntDocumento'      => $ctx['consecutivo'],
            'DatFecha'          => $this->resolver($cfg['fecha']       ?? '{pedido.fecha}', $ctx),
            'DatVencimiento'    => $this->resolver($cfg['vencimiento'] ?? '{pedido.fecha}', $ctx),
            'StrPlazo'          => $this->resolver($cfg['plazo']       ?? '0', $ctx),
            'StrTercero'        => $this->resolver($cfg['tercero']     ?? '{cliente.cedula}', $ctx),
            'IntValor'          => $this->resolver($cfg['valor']       ?? '{pedido.total}', $ctx),
            'IntSubtotal'       => $this->resolver($cfg['subtotal']    ?? '{pedido.total}', $ctx),
            'IntIva'            => $this->resolver($cfg['iva']         ?? 0, $ctx),
            'IntVrImpuesto1'    => $this->resolver($cfg['impuesto1']   ?? 0, $ctx),
            'IntTotal'          => $this->resolver($cfg['total']       ?? '{pedido.total}', $ctx),
            'IntNeto'           => $this->resolver($cfg['neto']        ?? '{pedido.total}', $ctx),
            'StrUsuarioGra'     => $this->resolver($cfg['usuario_grabador'] ?? 'admin', $ctx),
            'IntAno'            => $this->resolver($cfg['ano']         ?? '{ano}', $ctx),
            'IntPeriodo'        => $this->resolver($cfg['periodo']     ?? '{mes}', $ctx),
            'IntAnoCartera'     => $this->resolver($cfg['ano_cartera'] ?? '{ano}', $ctx),
            'IntPeriodoCartera' => $this->resolver($cfg['periodo_cartera'] ?? '{mes}', $ctx),
            'IntBodega'         => $this->resolver($cfg['bodega']      ?? 1, $ctx),
            'StrSucursal'       => $this->resolver($cfg['sucursal']    ?? '0', $ctx),
            'StrCcosto'         => $this->resolver($cfg['ccosto']      ?? '0', $ctx),
            'StrSubCcosto'      => $this->resolver($cfg['subccosto']   ?? '0', $ctx),
            'IntCartera'        => $this->resolver($cfg['cartera']     ?? 1, $ctx),
            'DatFechaGra'       => $this->resolver($cfg['fecha_grabacion'] ?? '{pedido.fecha_hora}', $ctx),
            'IntTranAux'        => $this->resolver($cfg['tran_aux']    ?? 0, $ctx),
            'IntDocRef'         => $this->resolver($cfg['doc_ref']     ?? 0, $ctx),
        ];
    }

    /**
     * Mapeo del detalle por línea → array [nombreColumna => valorResuelto].
     */
    private function camposDetalle(array $cfg, array $ctx): array
    {
        $det = $cfg['detalle'] ?? [];
        return [
            'IntEmpresa'         => $this->resolver($det['empresa']      ?? $cfg['empresa']     ?? 1, $ctx),
            'IntTransaccion'     => $this->resolver($det['transaccion']  ?? $cfg['transaccion'] ?? '009', $ctx),
            'IntDocumento'       => $ctx['consecutivo'],
            'StrProducto'        => $this->resolver($det['producto']     ?? '{detalle.codigo}', $ctx),
            'IntBodega'          => $this->resolver($det['bodega']       ?? $cfg['bodega']      ?? 1, $ctx),
            'IntCantidadDoc'     => $this->resolver($det['cantidad_doc'] ?? '{detalle.cantidad}', $ctx),
            'IntCantidad'        => $this->resolver($det['cantidad']     ?? '{detalle.cantidad}', $ctx),
            'StrUnidad'          => $this->resolver($det['unidad']       ?? '{detalle.unidad}', $ctx),
            'IntValorUnitario'   => $this->resolver($det['valor_unitario'] ?? '{detalle.precio}', $ctx),
            'IntValorTotal'      => $this->resolver($det['valor_total']  ?? '{detalle.subtotal}', $ctx),
            'IntValorIva'        => $this->resolver($det['valor_iva']    ?? 0, $ctx),
            'IntVrImpuesto1'     => $this->resolver($det['impuesto1']    ?? 0, $ctx),
            'StrSucursal'        => $this->resolver($det['sucursal']     ?? $cfg['sucursal']    ?? '0', $ctx),
            'StrCCosto'          => $this->resolver($det['ccosto']       ?? $cfg['ccosto']      ?? '0', $ctx),
            'StrSubCCosto'       => $this->resolver($det['subccosto']    ?? $cfg['subccosto']   ?? '0', $ctx),
            'StrSerie'           => $this->resolver($det['serie']        ?? '0', $ctx),
            'IntPorDescuento'    => $this->resolver($det['por_descuento']    ?? 1, $ctx),
            'IntValorDescuento'  => $this->resolver($det['valor_descuento']  ?? 0, $ctx),
        ];
    }

    /**
     * Construye un INSERT INTO tabla (col1, col2,...) VALUES (:col1, :col2, ...)
     * a partir del array [columna => valor].
     */
    private function construirInsert(string $tabla, array $campos): array
    {
        $cols = array_keys($campos);
        $placeholders = array_map(fn ($c) => ':' . $c, $cols);

        $sql = "INSERT INTO {$tabla} (" . implode(', ', $cols) . ")\n"
             . "VALUES (" . implode(', ', $placeholders) . ")";

        $params = [];
        foreach ($campos as $col => $val) {
            $params[':' . $col] = $val;
        }

        return [$sql, $params];
    }

    /**
     * Resuelve un valor que puede ser literal o contener {variable}.
     * Si todo el valor es {variable}, devuelve el tipo nativo.
     * Si tiene texto + variables, devuelve string interpolado.
     */
    private function resolver($valor, array $ctx)
    {
        if (!is_string($valor)) return $valor;

        // Si es exactamente {variable}, devolver tipo nativo
        if (preg_match('/^\{([\w\.]+)\}$/', $valor, $m)) {
            return $ctx[$m[1]] ?? '';
        }

        // Interpolación de placeholders dentro de un string
        return preg_replace_callback('/\{([\w\.]+)\}/', function ($m) use ($ctx) {
            return (string) ($ctx[$m[1]] ?? '');
        }, $valor);
    }

    /**
     * Calcula el siguiente IntDocumento haciendo MAX + 1.
     * Si la BD tiene constraint UNIQUE en (Empresa, Transaccion, Documento)
     * filtramos también por esos.
     */
    private function siguienteConsecutivo(PDO $pdo, string $tabla, array $cfg): int
    {
        $empresa     = (int) ($cfg['empresa']     ?? 1);
        $transaccion = (string) ($cfg['transaccion'] ?? '009');
        $minimo      = (int) ($cfg['consecutivo_inicial'] ?? 1);

        try {
            $stmt = $pdo->prepare("
                SELECT MAX(IntDocumento) AS maxDoc
                FROM {$tabla}
                WHERE IntEmpresa = :emp AND IntTransaccion = :tran
            ");
            $stmt->execute([':emp' => $empresa, ':tran' => $transaccion]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $max = (int) ($row['maxDoc'] ?? 0);
            return max($max + 1, $minimo);
        } catch (\Throwable $e) {
            Log::warning('No se pudo calcular consecutivo, usando minimo: ' . $e->getMessage());
            return $minimo;
        }
    }

    /**
     * Identificador del tercero (cliente) en el ERP.
     * Si el pedido tiene cliente con cédula, usar la cédula. Si no, usar
     * el teléfono normalizado como fallback.
     */
    private function resolverTercero(Pedido $pedido): string
    {
        if ($pedido->cliente && !empty($pedido->cliente->cedula)) {
            return (string) $pedido->cliente->cedula;
        }
        return (string) ($pedido->telefono_whatsapp
            ?? $pedido->telefono_contacto
            ?? $pedido->telefono
            ?? '0');
    }

    /**
     * Genera un SQL legible (con valores reemplazados) para guardar en log.
     * No usar para ejecución real — solo auditoría.
     */
    private function sqlConValores(string $sql, array $params): string
    {
        foreach ($params as $key => $val) {
            $val = is_string($val) ? "'{$val}'" : (string) $val;
            $sql = str_replace($key, $val, $sql);
        }
        return $sql;
    }
}
