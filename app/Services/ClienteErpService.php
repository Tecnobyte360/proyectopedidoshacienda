<?php

namespace App\Services;

use App\Models\Integracion;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Verifica si un cliente existe en la BD del ERP del cliente y lo crea
 * si es necesario.
 *
 * Configuración por integración (config.cliente_lookup):
 *
 * {
 *   "activo": true,
 *   "tabla": "TblTerceros",
 *   "columna_id": "StrIdTercero",       // columna donde guardas el NIT/cédula
 *   "columna_telefono": "StrCelular",   // (opcional) para buscar por teléfono
 *   "mapeo_insert": {                   // qué columnas y valores al crear
 *     "StrIdTercero": "{cliente.cedula}",
 *     "StrNombre":    "{cliente.nombre}",
 *     "StrCelular":   "{cliente.telefono}",
 *     "StrDireccion": "{cliente.direccion}"
 *   },
 *   "campos_requeridos": ["nombre","direccion","email"]  // datos que pedirá el bot
 * }
 */
class ClienteErpService
{
    public function __construct(
        private IntegracionSyncService $sync,
    ) {}

    /**
     * ¿Existe un cliente con esta cédula o teléfono en el ERP?
     * Retorna los datos si existe, null si no.
     */
    /**
     * 💰 Devuelve el número de LISTA DE PRECIOS (1..8) del cliente según HGI.
     * El cliente tiene un IntTipoTercero → ese tipo define un IntPrecio (lista).
     * Devuelve null si no se puede resolver (usar precio base).
     */
    public function obtenerListaPrecioCliente(Integracion $integracion, string $cedula): ?int
    {
        if (trim($cedula) === '') return null;
        $cfg = $integracion->config['cliente_lookup'] ?? [];
        if (!($cfg['activo'] ?? false)) return null;

        try {
            $pdo = $this->sync->conectar($integracion);
            // Ter.IntTipoTercero → Tip.IntPrecio (número de lista 1..8)
            $sql = "SELECT TOP 1 Tip.IntPrecio AS lista
                    FROM TblTerceros Ter
                    LEFT JOIN TblTiposTercero Tip ON Tip.IntIdTipoTercero = Ter.IntTipoTercero
                    WHERE Ter.StrIdTercero = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => trim($cedula)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lista = $row ? (int) ($row['lista'] ?? 0) : 0;
            return ($lista >= 1 && $lista <= 8) ? $lista : null;
        } catch (\Throwable $e) {
            Log::warning('ClienteErpService::obtenerListaPrecioCliente falló — ' . $e->getMessage(), [
                'integracion_id' => $integracion->id, 'cedula' => $cedula,
            ]);
            return null;
        }
    }

    public function buscar(Integracion $integracion, ?string $cedula = null, ?string $telefono = null): ?array
    {
        $cfg = $integracion->config['cliente_lookup'] ?? [];
        if (!($cfg['activo'] ?? false)) return null;

        $tabla    = trim((string) ($cfg['tabla'] ?? 'TblTerceros'));
        $colId    = trim((string) ($cfg['columna_id'] ?? 'StrTercero'));
        $colTel   = trim((string) ($cfg['columna_telefono'] ?? ''));
        if ($tabla === '' || $colId === '') return null;

        $resultado = null;
        $error = null;

        try {
            $pdo = $this->sync->conectar($integracion);

            // Primero por cédula (id principal)
            if (!empty($cedula)) {
                $stmt = $pdo->prepare("SELECT TOP 1 * FROM {$tabla} WHERE {$colId} = :id");
                $stmt->execute([':id' => $cedula]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $resultado = $row;
            }

            // Si no encontró por cédula y hay columna de teléfono configurada
            if (!$resultado && !empty($telefono) && $colTel !== '') {
                $stmt = $pdo->prepare("SELECT TOP 1 * FROM {$tabla} WHERE {$colTel} = :tel");
                $stmt->execute([':tel' => $telefono]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $resultado = $row;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('ClienteErpService::buscar falló — ' . $e->getMessage(), [
                'integracion_id' => $integracion->id,
                'cedula'         => $cedula,
                'telefono'       => $telefono,
            ]);
        }

        // Registrar en log para auditoría visual
        $this->registrarLog($integracion, [
            'accion'     => 'buscar',
            'cedula'     => $cedula,
            'telefono'   => $telefono,
            'encontrado' => $resultado !== null,
            'exitoso'    => $error === null,
            'datos_cliente_erp' => $resultado,
            'error_mensaje'     => $error,
        ]);

        return $resultado;
    }

    /**
     * Crea el cliente en la BD del ERP. Devuelve true si se creó OK.
     */
    public function crear(Integracion $integracion, array $datos): bool
    {
        $cfg = $integracion->config['cliente_lookup'] ?? [];
        if (!($cfg['activo'] ?? false)) return false;

        $tabla = trim((string) ($cfg['tabla'] ?? 'TblTerceros'));
        $mapeo = $cfg['mapeo_insert'] ?? [];

        if ($tabla === '' || empty($mapeo)) {
            Log::warning('ClienteErpService::crear sin mapeo_insert configurado');
            return false;
        }

        $error = null;
        $exitoso = false;

        try {
            $pdo = $this->sync->conectar($integracion);

            // Resolver cada valor del mapeo (literal o {variable})
            $contexto = $this->construirContextoCliente($datos);
            $columnas = [];
            $params   = [];
            foreach ($mapeo as $col => $valor) {
                $resuelto = $this->resolver($valor, $contexto);
                $columnas[$col] = $resuelto;
                $params[':' . $col] = $resuelto;
            }

            $sql = "INSERT INTO {$tabla} (" . implode(', ', array_keys($columnas)) . ")\n"
                 . "VALUES (" . implode(', ', array_map(fn ($c) => ':' . $c, array_keys($columnas))) . ")";

            // 🔧 Mismo patrón que IntegracionExportService::ejecutarInserts:
            // Desactivar triggers + constraints, hacer el INSERT, y reactivar.
            // Sin esto, SGI aborta el batch con "transaction ended in the trigger".
            try {
                $pdo->exec("DISABLE TRIGGER ALL ON {$tabla}");
                $pdo->exec("ALTER TABLE {$tabla} NOCHECK CONSTRAINT ALL");
                Log::info("🔧 Triggers + Constraints DESACTIVADOS en {$tabla} (cliente)");
            } catch (\Throwable $eDis) {
                Log::warning("No se pudieron desactivar triggers en {$tabla}: " . $eDis->getMessage());
            }

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $exitoso = true;

                Log::info('✅ Cliente creado en ERP', [
                    'integracion_id' => $integracion->id,
                    'tabla'          => $tabla,
                    'datos'          => $datos,
                ]);
            } finally {
                // Reactivar triggers SIEMPRE (incluso si el INSERT falla)
                try {
                    $pdo->exec("ENABLE TRIGGER ALL ON {$tabla}");
                    $pdo->exec("ALTER TABLE {$tabla} CHECK CONSTRAINT ALL");
                    Log::info("✓ Triggers + Constraints REACTIVADOS en {$tabla} (cliente)");
                } catch (\Throwable $eEn) {
                    Log::error("⚠️ NO SE PUDIERON REACTIVAR EN {$tabla}: " . $eEn->getMessage()
                        . " — Reactiva manualmente: ENABLE TRIGGER ALL ON {$tabla}; ALTER TABLE {$tabla} CHECK CONSTRAINT ALL");
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('❌ Error al crear cliente en ERP: ' . $error, [
                'integracion_id' => $integracion->id,
                'datos'          => $datos,
            ]);
        }

        $this->registrarLog($integracion, [
            'accion'        => 'crear',
            'cedula'        => $datos['cedula'] ?? null,
            'telefono'      => $datos['telefono'] ?? null,
            'nombre'        => $datos['nombre'] ?? null,
            'direccion'     => $datos['direccion'] ?? null,
            'exitoso'       => $exitoso,
            'error_mensaje' => $error,
        ]);

        return $exitoso;
    }

    /**
     * Registra cada operación de búsqueda/creación de cliente en el ERP
     * para auditoría visual desde /integraciones/clientes-erp.
     */
    private function registrarLog(Integracion $integracion, array $data): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('cliente_erp_lookups')->insert([
                'tenant_id'         => $integracion->tenant_id,
                'integracion_id'    => $integracion->id,
                'pedido_id'         => $data['pedido_id'] ?? null,
                'accion'            => $data['accion'] ?? 'buscar',
                'encontrado'        => (bool) ($data['encontrado'] ?? false),
                'exitoso'           => (bool) ($data['exitoso'] ?? true),
                'cedula'            => $data['cedula']    ?? null,
                'telefono'          => $data['telefono']  ?? null,
                'nombre'            => $data['nombre']    ?? null,
                'direccion'         => $data['direccion'] ?? null,
                'datos_cliente_erp' => $this->serializarJsonSeguro($data['datos_cliente_erp'] ?? null),
                'error_mensaje'     => $data['error_mensaje'] ?? null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            // No es crítico — solo log warning
            Log::warning('No se pudo registrar cliente_erp_lookup: ' . $e->getMessage());
        }
    }

    /**
     * Serializa a JSON tolerando UTF-8 inválido y arrays con datos del SGI.
     * Si por cualquier motivo no se puede serializar, devuelve null en vez
     * de un string roto que MySQL rechaza como "Invalid JSON text".
     */
    private function serializarJsonSeguro($valor): ?string
    {
        if ($valor === null) return null;

        // Si ya es un string que parece JSON válido, validar y devolver.
        if (is_string($valor)) {
            json_decode($valor);
            return json_last_error() === JSON_ERROR_NONE ? $valor : null;
        }

        if (!is_array($valor) && !is_object($valor)) {
            return json_encode((string) $valor);
        }

        // Sanitizar valores no-UTF8 dentro del array (SGI a veces devuelve Latin-1)
        $limpio = $this->sanitizarRecursivo($valor);

        $json = json_encode($limpio, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) return null;

        // Validar que MySQL lo aceptaría
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }

    private function sanitizarRecursivo($v)
    {
        if (is_array($v)) {
            return array_map(fn ($x) => $this->sanitizarRecursivo($x), $v);
        }
        if (is_object($v)) {
            return array_map(fn ($x) => $this->sanitizarRecursivo($x), (array) $v);
        }
        if (is_string($v)) {
            if (!mb_check_encoding($v, 'UTF-8')) {
                $v = @mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1, Windows-1252') ?: '';
            }
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
        }
        return $v;
    }

    /**
     * Verifica si existe; si no, lo crea con los datos provistos.
     * Devuelve true si al final el cliente existe.
     */
    public function asegurarCliente(Integracion $integracion, array $datos): bool
    {
        $existe = $this->buscar(
            $integracion,
            $datos['cedula'] ?? null,
            $datos['telefono'] ?? null
        );
        if ($existe) return true;

        return $this->crear($integracion, $datos);
    }

    /**
     * Actualiza SOLO la dirección de un cliente que YA EXISTE en el ERP (HGI).
     * No crea clientes. Devuelve true si actualizó alguna fila (el cliente existía).
     *
     * @param  string|null $cedula    Cédula (columna_id)
     * @param  string|null $telefono  Teléfono (columna_telefono)
     * @param  string      $direccion Nueva dirección
     */
    public function actualizarDireccion(Integracion $integracion, ?string $cedula, ?string $telefono, string $direccion): bool
    {
        $cfg = $integracion->config['cliente_lookup'] ?? [];
        if (!($cfg['activo'] ?? false)) return false;

        $direccion = trim($direccion);
        if ($direccion === '' || (empty($cedula) && empty($telefono))) return false;

        $tabla  = trim((string) ($cfg['tabla'] ?? 'TblTerceros'));
        $colId  = trim((string) ($cfg['columna_id'] ?? 'StrTercero'));
        $colTel = trim((string) ($cfg['columna_telefono'] ?? ''));

        // Columna de dirección = la que en mapeo_insert apunta a {cliente.direccion}.
        $colDir = null;
        foreach (($cfg['mapeo_insert'] ?? []) as $col => $val) {
            if (is_string($val) && str_contains($val, 'cliente.direccion')) { $colDir = $col; break; }
        }
        if (!$colDir || $tabla === '') return false;

        $actualizado = false;
        $error = null;
        try {
            $pdo = $this->sync->conectar($integracion);

            if (!empty($cedula)) {
                $stmt = $pdo->prepare("UPDATE {$tabla} SET {$colDir} = :dir WHERE {$colId} = :id");
                $stmt->execute([':dir' => $direccion, ':id' => $cedula]);
                $actualizado = $stmt->rowCount() > 0;
            }
            if (!$actualizado && !empty($telefono) && $colTel !== '') {
                $stmt = $pdo->prepare("UPDATE {$tabla} SET {$colDir} = :dir WHERE {$colTel} = :tel");
                $stmt->execute([':dir' => $direccion, ':tel' => $telefono]);
                $actualizado = $stmt->rowCount() > 0;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('ClienteErpService::actualizarDireccion falló — ' . $e->getMessage(), [
                'integracion_id' => $integracion->id,
                'cedula'         => $cedula,
                'telefono'       => $telefono,
            ]);
        }

        $this->registrarLog($integracion, [
            'accion'        => 'actualizar_direccion',
            'cedula'        => $cedula,
            'telefono'      => $telefono,
            'direccion'     => $direccion,
            'encontrado'    => $actualizado,
            'exitoso'       => $error === null,
            'error_mensaje' => $error,
        ]);

        return $actualizado;
    }

    private function construirContextoCliente(array $datos): array
    {
        return [
            'cliente.cedula'    => (string) ($datos['cedula']    ?? ''),
            'cliente.nombre'    => (string) ($datos['nombre']    ?? ''),
            'cliente.email'     => (string) ($datos['email']     ?? ''),
            'cliente.telefono'  => (string) ($datos['telefono']  ?? ''),
            'cliente.direccion' => (string) ($datos['direccion'] ?? ''),
            'cliente.ciudad'    => (string) ($datos['ciudad']    ?? ''),
            'cliente.barrio'    => (string) ($datos['barrio']    ?? ''),
        ];
    }

    private function resolver($valor, array $ctx)
    {
        if (!is_string($valor)) return $valor;
        if (preg_match('/^\{([\w\.]+)\}$/', $valor, $m)) {
            return $ctx[$m[1]] ?? '';
        }
        return preg_replace_callback('/\{([\w\.]+)\}/', function ($m) use ($ctx) {
            return (string) ($ctx[$m[1]] ?? '');
        }, $valor);
    }
}
