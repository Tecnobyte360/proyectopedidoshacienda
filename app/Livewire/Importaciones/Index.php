<?php

namespace App\Livewire\Importaciones;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

/**
 * Importaciones por tenant: productos y categorías desde CSV / XLSX.
 * Procesa en base64 para evitar livewire/upload-file (que da 401 en prod).
 */
class Index extends Component
{
    public string $tipo = 'productos';   // productos | categorias

    /** Resumen del último run. */
    public ?array $resumen = null;

    /** Errores de validación del último run (máx 50). */
    public array $errores = [];

    public function render()
    {
        return view('livewire.importaciones.index')->layout('layouts.app');
    }

    public function setTipo(string $tipo): void
    {
        $this->tipo = in_array($tipo, ['productos', 'categorias'], true) ? $tipo : 'productos';
        $this->resumen = null;
        $this->errores = [];
    }

    /**
     * Procesa un archivo (CSV o XLSX) enviado como data URL base64.
     * Formato esperado: productos → codigo,nombre,categoria,precio_base,unidad,descripcion_corta,descripcion,palabras_clave,activo,destacado,orden
     *                    categorias → nombre,descripcion,icono_emoji,color,orden,activo
     */
    public function importar(string $dataUrl, string $nombreArchivo): void
    {
        $this->resumen = null;
        $this->errores = [];

        if (!preg_match('/^data:([^;,]+)(?:;[^,]*)?;base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de archivo no reconocido.']);
            return;
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 10) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Archivo vacío o inválido.']);
            return;
        }
        if (strlen($bytes) > 20 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Archivo demasiado grande (máx 20 MB).']);
            return;
        }

        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION) ?: 'csv');
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Solo se aceptan archivos CSV o XLSX.']);
            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'import_') . '.' . $ext;
        file_put_contents($tmpPath, $bytes);

        try {
            $rows = $this->leerFilas($tmpPath, $ext);
            if (empty($rows)) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'El archivo está vacío.']);
                return;
            }

            // Primera fila = headers (normalizados)
            $headers = array_map(
                fn ($h) => Str::slug(mb_strtolower(trim((string) $h)), '_'),
                $rows[0]
            );
            $filasDatos = array_slice($rows, 1);

            if ($this->tipo === 'categorias') {
                $resultado = $this->importarCategorias($headers, $filasDatos);
            } else {
                $resultado = $this->importarProductos($headers, $filasDatos);
            }

            $this->resumen = $resultado;
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "✓ Importación terminada: {$resultado['creados']} creados, {$resultado['actualizados']} actualizados, {$resultado['omitidos']} con error."
            ]);
        } catch (\Throwable $e) {
            Log::error('Importación falló: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error procesando el archivo: ' . $e->getMessage()]);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function leerFilas(string $path, string $ext): array
    {
        if ($ext === 'csv') {
            $rows = [];
            $h = fopen($path, 'r');
            if (!$h) return [];
            // Detectar separador: coma o punto y coma
            $firstLine = fgets($h);
            rewind($h);
            $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            while (($r = fgetcsv($h, 0, $sep)) !== false) {
                $rows[] = $r;
            }
            fclose($h);
            return $rows;
        }

        // XLSX / XLS
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    private function importarCategorias(array $headers, array $filas): array
    {
        $creados = 0; $actualizados = 0; $omitidos = 0;

        foreach ($filas as $i => $fila) {
            if (!array_filter($fila, fn ($v) => trim((string) $v) !== '')) continue;   // fila vacía

            $row = $this->mapear($headers, $fila);
            $nombre = trim((string) ($row['nombre'] ?? ''));

            if ($nombre === '') {
                $this->errores[] = "Fila " . ($i + 2) . ": nombre vacío.";
                $omitidos++; continue;
            }

            $data = [
                'nombre'       => $nombre,
                'descripcion'  => trim((string) ($row['descripcion'] ?? '')) ?: null,
                'icono_emoji'  => trim((string) ($row['icono_emoji'] ?? $row['icono'] ?? $row['emoji'] ?? '')) ?: null,
                'color'        => trim((string) ($row['color'] ?? '')) ?: null,
                'orden'        => (int) ($row['orden'] ?? 0),
                'activo'       => $this->parseBool($row['activo'] ?? true),
            ];

            try {
                $existente = ProductoCategoria::where('nombre', $nombre)->first();
                if ($existente) {
                    $existente->update($data);
                    $actualizados++;
                } else {
                    ProductoCategoria::create($data);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $this->errores[] = "Fila " . ($i + 2) . " ({$nombre}): " . $e->getMessage();
                $omitidos++;
            }
        }

        return compact('creados', 'actualizados', 'omitidos');
    }

    private function importarProductos(array $headers, array $filas): array
    {
        $creados = 0; $actualizados = 0; $omitidos = 0;

        // Mapa de categoría por nombre (lazy cache)
        $categoriasCache = [];

        foreach ($filas as $i => $fila) {
            if (!array_filter($fila, fn ($v) => trim((string) $v) !== '')) continue;

            $row = $this->mapear($headers, $fila);

            $codigo = trim((string) ($row['codigo'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));

            if ($nombre === '') {
                $this->errores[] = "Fila " . ($i + 2) . ": nombre vacío.";
                $omitidos++; continue;
            }

            // Resolver categoría por nombre (si se especifica)
            $categoriaId = null;
            $catNombre = trim((string) ($row['categoria'] ?? $row['categoria_nombre'] ?? ''));
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

            $palabras = $this->parseArray($row['palabras_clave'] ?? null);

            $data = [
                'categoria_id'       => $categoriaId,
                'codigo'             => $codigo ?: null,
                'nombre'             => $nombre,
                'descripcion'        => trim((string) ($row['descripcion'] ?? '')) ?: null,
                'descripcion_corta'  => trim((string) ($row['descripcion_corta'] ?? '')) ?: null,
                'unidad'             => trim((string) ($row['unidad'] ?? 'unidad')) ?: 'unidad',
                'precio_base'        => $this->parseDecimal($row['precio_base'] ?? $row['precio'] ?? 0),
                'palabras_clave'     => $palabras,
                'activo'             => $this->parseBool($row['activo'] ?? true),
                'destacado'          => $this->parseBool($row['destacado'] ?? false),
                'orden'              => (int) ($row['orden'] ?? 0),
            ];

            try {
                // Match por código si existe; si no, por nombre exacto.
                $existente = null;
                if ($codigo) {
                    $existente = Producto::where('codigo', $codigo)->first();
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
                $this->errores[] = "Fila " . ($i + 2) . " ({$nombre}): " . $e->getMessage();
                $omitidos++;
            }
        }

        return compact('creados', 'actualizados', 'omitidos');
    }

    private function mapear(array $headers, array $fila): array
    {
        $out = [];
        foreach ($headers as $idx => $h) {
            $out[$h] = $fila[$idx] ?? null;
        }
        return $out;
    }

    private function parseBool($v): bool
    {
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'si', 'sí', 'true', 'yes', 'activo', 'x'], true);
    }

    private function parseDecimal($v): float
    {
        $s = trim((string) $v);
        if ($s === '') return 0.0;
        // Soportar "12.500,00" o "12500.00" o "12500"
        $s = str_replace(['$', ' '], '', $s);
        if (substr_count($s, ',') === 1 && substr_count($s, '.') > 0) {
            // Formato es-CO: 12.500,00 → 12500.00
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
            $s = str_replace(',', '.', $s);
        }
        return (float) $s;
    }

    private function parseArray($v): array
    {
        if (is_array($v)) return $v;
        $s = trim((string) $v);
        if ($s === '') return [];
        // Separar por coma o pipe
        $partes = preg_split('/[,|]/', $s);
        return array_values(array_filter(array_map('trim', $partes)));
    }

    /**
     * Descarga una plantilla CSV con los headers correctos.
     */
    public function descargarPlantilla(string $tipo)
    {
        $headers = $tipo === 'categorias'
            ? ['nombre', 'descripcion', 'icono_emoji', 'color', 'orden', 'activo']
            : ['codigo', 'nombre', 'categoria', 'unidad', 'precio_base', 'descripcion_corta', 'descripcion', 'palabras_clave', 'activo', 'destacado', 'orden'];

        $ejemplo = $tipo === 'categorias'
            ? ['Carnes', 'Cortes frescos de res, cerdo y pollo', '🥩', '#d68643', 1, 'si']
            : ['P001', 'Pechuga de pollo', 'Carnes', 'lb', 15000, 'Pechuga fresca', 'Pechuga de pollo fresca, sin piel', 'pollo,pechuga,blanca', 'si', 'no', 1];

        $filename = "plantilla_{$tipo}.csv";
        $content  = implode(',', $headers) . "\n" . implode(',', $ejemplo) . "\n";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
