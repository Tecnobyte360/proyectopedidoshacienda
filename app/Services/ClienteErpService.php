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
    public function buscar(Integracion $integracion, ?string $cedula = null, ?string $telefono = null): ?array
    {
        $cfg = $integracion->config['cliente_lookup'] ?? [];
        if (!($cfg['activo'] ?? false)) return null;

        $tabla    = trim((string) ($cfg['tabla'] ?? 'TblTerceros'));
        $colId    = trim((string) ($cfg['columna_id'] ?? 'StrTercero'));
        $colTel   = trim((string) ($cfg['columna_telefono'] ?? ''));
        if ($tabla === '' || $colId === '') return null;

        try {
            $pdo = $this->sync->conectar($integracion);

            // Primero por cédula (id principal)
            if (!empty($cedula)) {
                $stmt = $pdo->prepare("SELECT TOP 1 * FROM {$tabla} WHERE {$colId} = :id");
                $stmt->execute([':id' => $cedula]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }

            // Si no encontró por cédula y hay columna de teléfono configurada
            if (!empty($telefono) && $colTel !== '') {
                $stmt = $pdo->prepare("SELECT TOP 1 * FROM {$tabla} WHERE {$colTel} = :tel");
                $stmt->execute([':tel' => $telefono]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('ClienteErpService::buscar falló — ' . $e->getMessage(), [
                'integracion_id' => $integracion->id,
                'cedula'         => $cedula,
                'telefono'       => $telefono,
            ]);
            return null;
        }
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

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            Log::info('✅ Cliente creado en ERP', [
                'integracion_id' => $integracion->id,
                'tabla'          => $tabla,
                'datos'          => $datos,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('❌ Error al crear cliente en ERP: ' . $e->getMessage(), [
                'integracion_id' => $integracion->id,
                'datos'          => $datos,
            ]);
            return false;
        }
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
