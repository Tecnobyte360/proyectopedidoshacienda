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
 * Estructura de TblDocumentos esperada:
 *   IntEmpresa, IntTransaccion, IntDocumento, DatFecha, DatVencimiento,
 *   StrPlazo, StrTercero, IntValor, IntSubtotal, IntIva, IntVrImpuesto1,
 *   IntTotal, IntNeto, StrUsuarioGra, IntAno, IntPeriodo, IntAnoCartera,
 *   IntPeriodoCartera, IntBodega, StrSucursal, StrCcosto, StrSubCcosto,
 *   IntCartera, DatFechaGra, IntTranAux, IntDocRef
 *
 * El mapeo de constantes (IntEmpresa, IntTransaccion, IntBodega, etc) se
 * configura en el campo `config` del registro de Integracion.
 *
 * Ejemplo de config JSON:
 * {
 *   "host": "192.168.1.10",
 *   "port": "1433",
 *   "database": "ERP_HACIENDA",
 *   "username": "...",
 *   "password": "...",
 *   "export": {
 *     "tabla":          "TblDocumentos",
 *     "empresa":        1,
 *     "transaccion":    "009",
 *     "bodega":         41,
 *     "cartera":        1,
 *     "usuario_grabador":"admin",
 *     "consecutivo_inicial": 600
 *   }
 * }
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
        $tabla = $cfg['tabla'] ?? 'TblDocumentos';

        // Calcular consecutivo (IntDocumento)
        // Estrategia: tomar MAX(IntDocumento) actual + 1.
        // Es atómico-suficiente para un cliente con tráfico bajo. Para alto
        // tráfico convendría una tabla de consecutivos con SELECT FOR UPDATE.
        $pdo = $this->sync->conectar($integracion);
        $documentoId = $this->siguienteConsecutivo($pdo, $tabla, $cfg);

        // Datos del pedido para el INSERT
        $hoy = $pedido->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $hoyHora = $pedido->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');
        $tercero = $this->resolverTercero($pedido);
        $total   = (float) $pedido->total;

        // Calcular subtotal/IVA si la empresa maneja IVA. Por ahora, total
        // sin desglose (el bot no captura IVA, todo va como neto).
        $subtotal     = $total;
        $iva          = 0;
        $vrImpuesto1  = 0;
        $neto         = $total;
        $valor        = $total;

        $ano        = (int) ($cfg['ano']        ?? date('Y'));
        $periodo    = (int) ($cfg['periodo']    ?? date('n'));
        $anoCartera = (int) ($cfg['ano_cartera'] ?? $ano);
        $perCartera = (int) ($cfg['periodo_cartera'] ?? $periodo);

        $params = [
            ':intEmpresa'        => (int) ($cfg['empresa']     ?? 1),
            ':intTransaccion'    => (string) ($cfg['transaccion'] ?? '009'),
            ':intDocumento'      => $documentoId,
            ':datFecha'          => $hoy,
            ':datVencimiento'    => $hoy,
            ':strPlazo'          => (string) ($cfg['plazo']    ?? '0'),
            ':strTercero'        => $tercero,
            ':intValor'          => $valor,
            ':intSubtotal'       => $subtotal,
            ':intIva'            => $iva,
            ':intVrImpuesto1'    => $vrImpuesto1,
            ':intTotal'          => $total,
            ':intNeto'           => $neto,
            ':strUsuarioGra'     => (string) ($cfg['usuario_grabador'] ?? 'admin'),
            ':intAno'            => $ano,
            ':intPeriodo'        => $periodo,
            ':intAnoCartera'     => $anoCartera,
            ':intPeriodoCartera' => $perCartera,
            ':intBodega'         => (int) ($cfg['bodega']      ?? 1),
            ':strSucursal'       => (string) ($cfg['sucursal'] ?? '0'),
            ':strCcosto'         => (string) ($cfg['ccosto']   ?? '0'),
            ':strSubCcosto'      => (string) ($cfg['subccosto'] ?? '0'),
            ':intCartera'        => (int) ($cfg['cartera']     ?? 1),
            ':datFechaGra'       => $hoyHora,
            ':intTranAux'        => (int) ($cfg['tran_aux']    ?? 0),
            ':intDocRef'         => (int) ($cfg['doc_ref']     ?? 0),
        ];

        $sql = "INSERT INTO {$tabla} (
            IntEmpresa, IntTransaccion, IntDocumento, DatFecha, DatVencimiento,
            StrPlazo, StrTercero, IntValor, IntSubtotal, IntIva, IntVrImpuesto1,
            IntTotal, IntNeto, StrUsuarioGra, IntAno, IntPeriodo, IntAnoCartera,
            IntPeriodoCartera, IntBodega, StrSucursal, StrCcosto, StrSubCcosto,
            IntCartera, DatFechaGra, IntTranAux, IntDocRef
        ) VALUES (
            :intEmpresa, :intTransaccion, :intDocumento, :datFecha, :datVencimiento,
            :strPlazo, :strTercero, :intValor, :intSubtotal, :intIva, :intVrImpuesto1,
            :intTotal, :intNeto, :strUsuarioGra, :intAno, :intPeriodo, :intAnoCartera,
            :intPeriodoCartera, :intBodega, :strSucursal, :strCcosto, :strSubCcosto,
            :intCartera, :datFechaGra, :intTranAux, :intDocRef
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Log de éxito
        $sqlPreview = $this->sqlConValores($sql, $params);
        DB::table('integracion_export_logs')->insert([
            'tenant_id'      => $pedido->tenant_id,
            'integracion_id' => $integracion->id,
            'pedido_id'      => $pedido->id,
            'estado'         => 'ok',
            'documento_id'   => $documentoId,
            'sql_ejecutado'  => $sqlPreview,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Log::info('✅ Pedido exportado al ERP', [
            'pedido_id'      => $pedido->id,
            'integracion_id' => $integracion->id,
            'tabla'          => $tabla,
            'documento_id'   => $documentoId,
        ]);

        return [
            'estado'       => 'ok',
            'documento_id' => $documentoId,
            'tabla'        => $tabla,
        ];
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
