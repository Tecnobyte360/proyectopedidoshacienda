<?php

namespace App\Services;

use App\Models\IntegracionConsulta;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Ejecuta consultas guardadas (IntegracionConsulta) contra la BD externa
 * de la integración con bindings seguros (PDO prepared statements).
 *
 * Soporta dos sintaxis de parámetros en el SQL:
 *   - Posicional:  WHERE codigo = ?
 *   - Nombrada:    WHERE codigo = :codigo
 *
 * Detecta automáticamente cuál usa el query.
 */
class ConsultaIntegracionService
{
    public function __construct(
        private IntegracionSyncService $syncService,
    ) {}

    /**
     * Ejecuta la consulta. Devuelve array uniforme:
     *   ok, total, columnas, filas, ejecuciones (acumulado), error?
     */
    public function ejecutar(IntegracionConsulta $consulta, array $params = [], int $limite = 200): array
    {
        $integracion = $consulta->integracion;
        if (!$integracion) {
            return ['ok' => false, 'error' => 'Integración no encontrada.', 'filas' => []];
        }
        if (!$integracion->activo) {
            return ['ok' => false, 'error' => 'Integración inactiva.', 'filas' => []];
        }

        try {
            // Conectar usando el mismo metodo que el SyncService (reflection no, hacerlo publico mejor)
            // Usamos un PDO directamente desde el config de la integracion
            $pdo = $this->conectarDesdeIntegracion($integracion);

            $sql = trim($consulta->query_sql);
            if ($sql === '') {
                return ['ok' => false, 'error' => 'Query vacío.', 'filas' => []];
            }

            // Detectar parametros tipo :nombre vs ?
            $usaNombrados = preg_match('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql);
            $bindings = [];

            if ($usaNombrados) {
                preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
                $names = array_unique($matches[1] ?? []);
                foreach ($names as $name) {
                    $bindings[$name] = $params[$name] ?? null;
                }
            } else {
                // Posicional ?: ordenamos por orden de aparición
                $defs = (array) ($consulta->parametros ?? []);
                foreach ($defs as $def) {
                    $key = $def['nombre'] ?? null;
                    if ($key) $bindings[] = $params[$key] ?? null;
                }
            }

            // Aplicar limite si el usuario lo permite y no esta hardcoded
            $sqlLimitado = $this->aplicarLimite($sql, $integracion->tipo, $limite);

            $stmt = $pdo->prepare($sqlLimitado);

            if ($usaNombrados) {
                foreach ($bindings as $k => $v) {
                    $stmt->bindValue(':' . $k, $v);
                }
            } else {
                foreach ($bindings as $i => $v) {
                    $stmt->bindValue($i + 1, $v);
                }
            }

            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columnas = !empty($filas) ? array_keys($filas[0]) : [];

            // Actualizar metricas
            $consulta->update([
                'ultima_ejecucion_at' => now(),
                'total_ejecuciones'   => ($consulta->total_ejecuciones ?? 0) + 1,
            ]);

            return [
                'ok'         => true,
                'total'      => count($filas),
                'columnas'   => $columnas,
                'filas'      => $filas,
                'parametros' => $bindings,
            ];
        } catch (\Throwable $e) {
            Log::warning('Consulta integracion fallo', [
                'consulta_id' => $consulta->id,
                'error'       => $e->getMessage(),
            ]);
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
                'filas' => [],
            ];
        }
    }

    /**
     * Crea un PDO desde la config de una integración — replica la lógica de
     * IntegracionSyncService::conectar pero accesible públicamente desde aquí.
     */
    private function conectarDesdeIntegracion(\App\Models\Integracion $i): PDO
    {
        $c = (array) ($i->config ?? []);
        $host = $c['host'] ?? '';
        $port = $c['port'] ?? null;
        $db   = $c['database'] ?? '';
        $user = $c['username'] ?? '';
        $pass = $c['password'] ?? '';

        $dsn = match ($i->tipo) {
            'sqlsrv' => "sqlsrv:Server={$host}" . ($port ? ",{$port}" : '') . ";Database={$db};Encrypt=no;TrustServerCertificate=yes",
            'pgsql'  => "pgsql:host={$host};" . ($port ? "port={$port};" : '') . "dbname={$db}",
            default  => "mysql:host={$host};" . ($port ? "port={$port};" : '') . "dbname={$db};charset=utf8mb4",
        };

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        return $pdo;
    }

    /**
     * Si el query no tiene LIMIT/TOP, le agrega uno para evitar resultados gigantes.
     */
    private function aplicarLimite(string $sql, string $tipo, int $n): string
    {
        $sql = rtrim(trim($sql), ';');
        if (preg_match('/\b(LIMIT|TOP|FETCH\s+NEXT)\b/i', $sql)) return $sql;

        if ($tipo === 'sqlsrv') {
            return preg_replace('/^\s*SELECT\s+/i', "SELECT TOP {$n} ", $sql, 1);
        }
        return $sql . " LIMIT {$n}";
    }
}
