<?php

namespace App\Services;

use App\Models\Integracion;
use App\Models\Producto;
use App\Models\ProductoCategoria;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Conecta a una BD externa del tenant (SQL Server via pdo_dblib, MySQL, PostgreSQL)
 * y sincroniza productos o categorías con el catálogo local.
 */
class IntegracionSyncService
{
    /**
     * Lista todas las tablas de la BD externa.
     * Devuelve ['ok'=>bool, 'mensaje'=>string, 'tablas'=>array<string>].
     */
    public function listarTablas(Integracion $integracion): array
    {
        try {
            $pdo = $this->conectar($integracion);
            $db  = $integracion->config['database'] ?? '';

            $sql = match ($integracion->tipo) {
                Integracion::TIPO_SQLSRV =>
                    "SELECT TABLE_SCHEMA + '.' + TABLE_NAME AS tabla
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_TYPE = 'BASE TABLE'
                     ORDER BY TABLE_SCHEMA, TABLE_NAME",
                Integracion::TIPO_MYSQL =>
                    "SELECT TABLE_NAME AS tabla
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = '" . addslashes($db) . "' AND TABLE_TYPE = 'BASE TABLE'
                     ORDER BY TABLE_NAME",
                Integracion::TIPO_PGSQL =>
                    "SELECT schemaname || '.' || tablename AS tabla
                     FROM pg_catalog.pg_tables
                     WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
                     ORDER BY schemaname, tablename",
                default => throw new RuntimeException('Tipo no soportado'),
            };

            $stmt = $pdo->query($sql);
            $tablas = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tabla');

            return ['ok' => true, 'mensaje' => count($tablas) . ' tablas encontradas', 'tablas' => $tablas];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage(), 'tablas' => []];
        }
    }

    /**
     * Lista las columnas de una tabla específica.
     * Devuelve ['ok'=>bool, 'columnas'=>array<['nombre'=>, 'tipo'=>]>, 'muestra'=>array].
     */
    public function describirTabla(Integracion $integracion, string $tabla): array
    {
        try {
            $pdo = $this->conectar($integracion);

            // Separar schema.tabla si aplica
            $schema = null;
            $tabla_solo = $tabla;
            if (str_contains($tabla, '.')) {
                [$schema, $tabla_solo] = explode('.', $tabla, 2);
            }

            $sql = match ($integracion->tipo) {
                Integracion::TIPO_SQLSRV =>
                    "SELECT COLUMN_NAME AS nombre, DATA_TYPE AS tipo, IS_NULLABLE AS nullable
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = " . $pdo->quote($tabla_solo)
                     . ($schema ? " AND TABLE_SCHEMA = " . $pdo->quote($schema) : '') . "
                     ORDER BY ORDINAL_POSITION",
                Integracion::TIPO_MYSQL =>
                    "SELECT COLUMN_NAME AS nombre, DATA_TYPE AS tipo, IS_NULLABLE AS nullable
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = " . $pdo->quote($tabla_solo) . "
                     ORDER BY ORDINAL_POSITION",
                Integracion::TIPO_PGSQL =>
                    "SELECT column_name AS nombre, data_type AS tipo, is_nullable AS nullable
                     FROM information_schema.columns
                     WHERE table_name = " . $pdo->quote($tabla_solo)
                     . ($schema ? " AND table_schema = " . $pdo->quote($schema) : '') . "
                     ORDER BY ordinal_position",
                default => throw new RuntimeException('Tipo no soportado'),
            };

            $stmt = $pdo->query($sql);
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Preview: 3 filas
            $preview = [];
            try {
                $tblRef = $integracion->tipo === Integracion::TIPO_SQLSRV
                    ? "[{$schema}].[{$tabla_solo}]"
                    : ($schema ? "\"{$schema}\".\"{$tabla_solo}\"" : "\"{$tabla_solo}\"");
                if ($integracion->tipo === Integracion::TIPO_MYSQL) {
                    $tblRef = "`{$tabla_solo}`";
                }
                $sql = $integracion->tipo === Integracion::TIPO_SQLSRV
                    ? "SELECT TOP 3 * FROM {$tblRef}"
                    : "SELECT * FROM {$tblRef} LIMIT 3";
                $preview = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) { /* preview opcional */ }

            return ['ok' => true, 'columnas' => $cols, 'muestra' => $preview];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage(), 'columnas' => [], 'muestra' => []];
        }
    }

    /**
     * Prueba la conexión y devuelve ['ok'=>bool, 'mensaje'=>string, 'muestra'=>array].
     * 'muestra' contiene las primeras 5 filas si el query funciona.
     */
    public function probarConexion(Integracion $integracion): array
    {
        try {
            $pdo = $this->conectar($integracion);
            $query = (string) ($integracion->config['query'] ?? '');
            if (trim($query) === '') {
                return ['ok' => true, 'mensaje' => 'Conexión OK — no hay query configurado para probar.', 'muestra' => []];
            }

            $stmt = $pdo->query($this->limitarQuery($query, $integracion->tipo, 5));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'ok' => true,
                'mensaje' => 'Conexión y query OK. Primeras ' . count($rows) . ' filas:',
                'muestra' => $rows,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage(), 'muestra' => []];
        }
    }

    /**
     * Ejecuta la sincronización según la entidad de la integración.
     * Devuelve ['ok'=>bool, 'creados'=>int, 'actualizados'=>int, 'errores'=>int, 'log'=>string].
     */
    public function sincronizar(Integracion $integracion): array
    {
        $inicio = microtime(true);

        try {
            $pdo   = $this->conectar($integracion);
            $query = (string) ($integracion->config['query'] ?? '');

            if (trim($query) === '') {
                throw new RuntimeException('No hay query configurado.');
            }

            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapeo = $integracion->config['mapeo'] ?? [];

            $resultado = $integracion->entidad === Integracion::ENTIDAD_CATEGORIAS
                ? $this->syncCategorias($rows, $mapeo)
                : $this->syncProductos($rows, $mapeo);

            $duracion = round(microtime(true) - $inicio, 2);
            $log = "✅ Sincronización OK en {$duracion}s\n"
                 . "  Filas leídas: " . count($rows) . "\n"
                 . "  Creados: {$resultado['creados']}\n"
                 . "  Actualizados: {$resultado['actualizados']}\n"
                 . "  Con error: {$resultado['errores']}";

            if (!empty($resultado['errores_detalle'])) {
                $log .= "\n\nPrimeros errores:\n" . implode("\n", array_slice($resultado['errores_detalle'], 0, 10));
            }

            $integracion->update([
                'ultima_sincronizacion_at'      => now(),
                'ultima_sincronizacion_estado'  => 'ok',
                'ultima_sincronizacion_log'     => $log,
                'total_registros_ultima_sync'   => count($rows),
            ]);

            return [
                'ok' => true,
                'creados'      => $resultado['creados'],
                'actualizados' => $resultado['actualizados'],
                'errores'      => $resultado['errores'],
                'log'          => $log,
            ];
        } catch (\Throwable $e) {
            Log::error('Sync integración falló', [
                'integracion_id' => $integracion->id,
                'error'          => $e->getMessage(),
            ]);

            $integracion->update([
                'ultima_sincronizacion_at'     => now(),
                'ultima_sincronizacion_estado' => 'error',
                'ultima_sincronizacion_log'    => '❌ Error: ' . $e->getMessage(),
            ]);

            return [
                'ok'           => false,
                'creados'      => 0,
                'actualizados' => 0,
                'errores'      => 0,
                'log'          => '❌ ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Crea una conexión PDO a la BD externa según el tipo.
     */
    private function conectar(Integracion $integracion): PDO
    {
        $c = $integracion->config;

        $host     = trim((string) ($c['host'] ?? ''));
        $port     = trim((string) ($c['port'] ?? ''));
        $database = trim((string) ($c['database'] ?? ''));
        $username = (string) ($c['username'] ?? '');
        $password = (string) ($c['password'] ?? '');

        if ($host === '' || $database === '') {
            throw new RuntimeException('Faltan host o database en la configuración.');
        }

        $dsn = match ($integracion->tipo) {
            Integracion::TIPO_MYSQL  => "mysql:host={$host}" . ($port ? ";port={$port}" : '') . ";dbname={$database};charset=utf8mb4",
            Integracion::TIPO_PGSQL  => "pgsql:host={$host}" . ($port ? ";port={$port}" : '') . ";dbname={$database}",
            Integracion::TIPO_SQLSRV => $this->dsnSqlServer($host, $port, $database),
            default => throw new RuntimeException("Tipo no soportado: {$integracion->tipo}"),
        };

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT           => 10,
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES  => true,
            ]);
            if ($integracion->tipo === Integracion::TIPO_SQLSRV) {
                // pdo_dblib suele devolver todo como string; forzamos UTF-8 si se puede.
                @$pdo->exec("SET TEXTSIZE 2147483647");
            }
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar: ' . $e->getMessage());
        }
    }

    private function dsnSqlServer(string $host, string $port, string $database): string
    {
        // Si el contenedor tiene pdo_dblib (FreeTDS), usamos dblib. Si tiene sqlsrv oficial, también.
        // El 'dblib' driver usa "host:port" en vez de ";port=".
        if (extension_loaded('pdo_dblib')) {
            $hp = $port ? "{$host}:{$port}" : $host;
            return "dblib:host={$hp};dbname={$database};charset=UTF-8";
        }
        if (extension_loaded('pdo_sqlsrv')) {
            return "sqlsrv:Server={$host}" . ($port ? ",{$port}" : '') . ";Database={$database}";
        }
        throw new RuntimeException('No hay driver PDO para SQL Server instalado (pdo_dblib o pdo_sqlsrv).');
    }

    /**
     * Añade LIMIT / TOP al query para previsualización, sin alterar queries complejos.
     */
    private function limitarQuery(string $query, string $tipo, int $n): string
    {
        $q = rtrim(trim($query), ';');
        // Si ya tiene LIMIT/TOP, lo dejamos.
        if (preg_match('/\b(LIMIT|TOP)\b/i', $q)) return $q;

        if ($tipo === Integracion::TIPO_SQLSRV) {
            // SELECT TOP n ...
            return preg_replace('/^\s*SELECT\s+/i', "SELECT TOP {$n} ", $q, 1);
        }
        return $q . " LIMIT {$n}";
    }

    private function syncProductos(array $rows, array $mapeo): array
    {
        $creados = 0; $actualizados = 0; $errores = 0;
        $erroresDetalle = [];
        $categoriasCache = [];

        foreach ($rows as $idx => $row) {
            try {
                $codigo = $this->valor($row, $mapeo, 'codigo');
                $nombre = trim((string) $this->valor($row, $mapeo, 'nombre'));

                if ($nombre === '') {
                    $erroresDetalle[] = "Fila " . ($idx + 1) . ": nombre vacío";
                    $errores++; continue;
                }

                // Resolver categoría por nombre
                $categoriaId = null;
                $catNombre = trim((string) $this->valor($row, $mapeo, 'categoria'));
                if ($catNombre !== '') {
                    if (!isset($categoriasCache[$catNombre])) {
                        $cat = ProductoCategoria::firstOrCreate(
                            ['nombre' => $catNombre],
                            ['activo' => true, 'orden' => 0]
                        );
                        $categoriasCache[$catNombre] = $cat->id;
                    }
                    $categoriaId = $categoriasCache[$catNombre];
                }

                $data = [
                    'categoria_id'       => $categoriaId,
                    'codigo'             => $codigo ? trim((string) $codigo) : null,
                    'nombre'             => $nombre,
                    'descripcion'        => $this->val($row, $mapeo, 'descripcion'),
                    'descripcion_corta'  => $this->val($row, $mapeo, 'descripcion_corta'),
                    'unidad'             => $this->val($row, $mapeo, 'unidad') ?: 'unidad',
                    'precio_base'        => (float) ($this->valor($row, $mapeo, 'precio_base') ?? 0),
                    'activo'             => true,
                ];

                $existente = null;
                if (!empty($data['codigo'])) {
                    $existente = Producto::where('codigo', $data['codigo'])->first();
                }
                if (!$existente) {
                    $existente = Producto::where('nombre', $nombre)->first();
                }

                if ($existente) {
                    $existente->update($data);
                    $actualizados++;
                } else {
                    Producto::create($data);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $erroresDetalle[] = "Fila " . ($idx + 1) . ": " . $e->getMessage();
                $errores++;
            }
        }

        return compact('creados', 'actualizados', 'errores', 'erroresDetalle') + ['errores_detalle' => $erroresDetalle];
    }

    private function syncCategorias(array $rows, array $mapeo): array
    {
        $creados = 0; $actualizados = 0; $errores = 0;
        $erroresDetalle = [];

        foreach ($rows as $idx => $row) {
            try {
                $nombre = trim((string) $this->valor($row, $mapeo, 'nombre'));
                if ($nombre === '') {
                    $erroresDetalle[] = "Fila " . ($idx + 1) . ": nombre vacío";
                    $errores++; continue;
                }

                $data = [
                    'nombre'      => $nombre,
                    'descripcion' => $this->val($row, $mapeo, 'descripcion'),
                    'activo'      => true,
                ];

                $existente = ProductoCategoria::where('nombre', $nombre)->first();
                if ($existente) {
                    $existente->update($data);
                    $actualizados++;
                } else {
                    ProductoCategoria::create($data);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $erroresDetalle[] = "Fila " . ($idx + 1) . ": " . $e->getMessage();
                $errores++;
            }
        }

        return compact('creados', 'actualizados', 'errores', 'erroresDetalle') + ['errores_detalle' => $erroresDetalle];
    }

    /**
     * Obtiene el valor de una fila usando el mapeo: campoDestino => columna_origen.
     * Ej: $mapeo = ['nombre' => 'Descripcion', 'codigo' => 'CodArt']
     */
    private function valor(array $row, array $mapeo, string $campoDestino)
    {
        $columnaOrigen = $mapeo[$campoDestino] ?? $campoDestino;

        // Match exact
        if (array_key_exists($columnaOrigen, $row)) return $row[$columnaOrigen];

        // Match case-insensitive
        foreach ($row as $k => $v) {
            if (strcasecmp($k, $columnaOrigen) === 0) return $v;
        }
        return null;
    }

    private function val(array $row, array $mapeo, string $campo): ?string
    {
        $v = $this->valor($row, $mapeo, $campo);
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
