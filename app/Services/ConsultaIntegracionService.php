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
            // Reutilizamos la conexión que ya funciona en IntegracionSyncService
            // (maneja pdo_dblib / pdo_sqlsrv, mysql, pgsql, etc).
            $pdo = $this->syncService->conectar($integracion);

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

            return $this->limpiarUtf8([
                'ok'         => true,
                'total'      => count($filas),
                'columnas'   => $columnas,
                'filas'      => $filas,
                'parametros' => $bindings,
            ]);
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
     * Limpia recursivamente strings con encoding distinto a UTF-8.
     * SQL Server / FreeTDS suele devolver Windows-1252 → Livewire/JSON revientan.
     */
    private function limpiarUtf8($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $this->stringUtf8($k) : $k;
                $out[$key] = $this->limpiarUtf8($v);
            }
            return $out;
        }
        if (is_string($value)) return $this->stringUtf8($value);
        return $value;
    }

    private function stringUtf8(string $s): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) return $s;
        $det = mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';
        return mb_convert_encoding($s, 'UTF-8', $det);
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
