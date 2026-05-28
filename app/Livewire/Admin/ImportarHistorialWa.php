<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\WhatsappImportTxtService;
use App\Services\WhatsappResolverService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * 📥 Importador MASIVO de exports .txt de WhatsApp Business app.
 *
 * Flujo:
 *   1. (Super-admin) elige tenant. Resto usa tenant actual.
 *   2. Arrastra UNO o MUCHOS .txt al recuadro.
 *      Cada archivo se lee en cliente con FileReader, se manda a procesarArchivo()
 *      que lo parsea y guarda el texto crudo en storage/app/imports-wa/{sesion}/
 *      (no quedan grandes blobs en el estado Livewire — solo metadatos).
 *   3. Tabla preview con todos los archivos: por fila usuario edita
 *      teléfono cliente + autor yo + nombre, ve KPIs y vista previa.
 *   4. Click "Importar todos" → procesa en lote los que tengan datos completos.
 *      Resultado por fila.
 */
class ImportarHistorialWa extends Component
{
    /** ID de sesión para aislar los temp files de este usuario */
    public string $sesionId = '';

    /** Slug del tenant destino */
    public string $tenantSlug = '';

    /** País por defecto para los teléfonos */
    public string $paisCodigo = '+57';

    /**
     * Lista de archivos cargados. Cada item:
     *   [
     *     'uid'           => string,        // hash único
     *     'nombre'        => string,
     *     'tamano'        => int,
     *     'total'         => int,
     *     'participantes' => string[],
     *     'rango_from'    => string,
     *     'rango_to'      => string,
     *     'preview'       => array,         // primeros 3 mensajes
     *     'autorYo'       => string,
     *     'telefono'      => string,
     *     'nombreCliente' => string,
     *     'estado'        => 'pendiente' | 'importado' | 'error' | 'omitido',
     *     'resultado'     => array,         // tras importar
     *     'error'         => string,
     *   ]
     */
    public array $archivos = [];

    /** Mensaje general */
    public string $aviso = '';
    public string $error = '';

    public function mount(): void
    {
        $tm = app(TenantManager::class);
        $tenant = method_exists($tm, 'current') ? $tm->current() : null;
        $this->tenantSlug = $tenant?->slug ?? '';

        // Sesión persistente por usuario para acumular archivos entre re-mounts
        $this->sesionId = session('importar_wa_sesion') ?? Str::random(16);
        session(['importar_wa_sesion' => $this->sesionId]);
    }

    /**
     * Llamado desde JS con el texto crudo del .txt leído en cliente.
     */
    public function procesarArchivo(string $nombre, string $texto): void
    {
        $this->aviso = '';
        $this->error = '';

        if (trim($texto) === '') {
            $this->error = "Archivo {$nombre} vacío.";
            return;
        }
        if (mb_strlen($texto) > 10 * 1024 * 1024) {
            $this->error = "Archivo {$nombre} supera 10 MB. Exporta sin medios.";
            return;
        }

        try {
            $svc = app(WhatsappImportTxtService::class);
            $analisis = $svc->parsearContenido($texto);

            if (empty($analisis['mensajes'])) {
                $this->error = "No se detectaron mensajes en {$nombre}.";
                return;
            }

            // Persistir texto crudo en disco para usarlo luego en la importación
            $uid = Str::random(12);
            Storage::disk('local')->put($this->rutaTemp($uid), $texto);

            // Auto-detectar nombre cliente del filename:
            //   "WhatsApp Chat con Juan Pérez.txt" → "Juan Pérez"
            $nombreCliente = '';
            if (preg_match('/(?:Chat con|Chat with|Chat de)\s+(.+?)\.txt$/iu', $nombre, $m)) {
                $nombreCliente = trim($m[1]);
            }

            // Auto-detectar teléfono del filename si tiene dígitos largos
            //   "WhatsApp Chat con +57 321 649 9744.txt" → "573216499744"
            $telefono = '';
            if (preg_match('/(\+?\d[\d\s\-\(\)]{6,})/', $nombre, $mm)) {
                $tel = preg_replace('/\D+/', '', $mm[1]);
                if (strlen($tel) >= 7 && strlen($tel) <= 15) {
                    $telefono = $tel;
                    // Si lo que detectamos como "nombre" es realmente el teléfono, limpiar
                    if (preg_replace('/\D+/', '', $nombreCliente) === $tel) {
                        $nombreCliente = '';
                    }
                }
            }

            // Auto-elegir "yo" si solo hay 2 participantes (asume el segundo es el negocio)
            $autorYo = '';
            if (count($analisis['participantes']) === 2) {
                $autorYo = $analisis['participantes'][1];
            } elseif (count($analisis['participantes']) === 1) {
                $autorYo = '';
            }

            $this->archivos[] = [
                'uid'           => $uid,
                'nombre'        => $nombre,
                'tamano'        => mb_strlen($texto),
                'total'         => $analisis['total'],
                'participantes' => $analisis['participantes'],
                'rango_from'    => $analisis['rango']['from'] ?? '',
                'rango_to'      => $analisis['rango']['to']   ?? '',
                'preview'       => array_slice($analisis['mensajes'], 0, 3),
                'autorYo'       => $autorYo,
                'telefono'      => $telefono,
                'nombreCliente' => $nombreCliente,
                'estado'        => 'pendiente',
                'resultado'     => [],
                'error'         => '',
            ];
        } catch (\Throwable $e) {
            $this->error = "Error procesando {$nombre}: " . $e->getMessage();
        }
    }

    public function removerArchivo(int $idx): void
    {
        if (!isset($this->archivos[$idx])) return;
        $uid = $this->archivos[$idx]['uid'];
        Storage::disk('local')->delete($this->rutaTemp($uid));
        array_splice($this->archivos, $idx, 1);
    }

    public function limpiarTodo(): void
    {
        foreach ($this->archivos as $a) {
            Storage::disk('local')->delete($this->rutaTemp($a['uid']));
        }
        $this->archivos = [];
        $this->aviso = '';
        $this->error = '';
    }

    public function importarTodos(): void
    {
        $this->aviso = '';
        $this->error = '';

        $tenant = Tenant::where('slug', $this->tenantSlug)->first();
        if (!$tenant) {
            $this->error = 'Tenant no encontrado.';
            return;
        }

        // connection_id opcional para trazabilidad
        $connId = null;
        try {
            $ids = app(WhatsappResolverService::class)->connectionIdsValidos();
            $connId = $ids[0] ?? null;
        } catch (\Throwable $e) { /* opcional */ }

        $svc = app(WhatsappImportTxtService::class);

        $okCount  = 0;
        $errCount = 0;
        $skipCount = 0;

        foreach ($this->archivos as $idx => $a) {
            // Saltar los ya importados
            if ($a['estado'] === 'importado') {
                $skipCount++;
                continue;
            }

            $tel = preg_replace('/\D+/', '', $a['telefono']);
            if (!$tel || strlen($tel) < 7) {
                $this->archivos[$idx]['estado'] = 'omitido';
                $this->archivos[$idx]['error']  = 'Sin teléfono';
                $skipCount++;
                continue;
            }
            if (empty($a['autorYo'])) {
                $this->archivos[$idx]['estado'] = 'omitido';
                $this->archivos[$idx]['error']  = 'Sin "yo" elegido';
                $skipCount++;
                continue;
            }

            // Re-parsear desde el archivo en disco (no conservamos en estado)
            $path = $this->rutaTemp($a['uid']);
            if (!Storage::disk('local')->exists($path)) {
                $this->archivos[$idx]['estado'] = 'error';
                $this->archivos[$idx]['error']  = 'Archivo temp perdido';
                $errCount++;
                continue;
            }

            try {
                $texto = Storage::disk('local')->get($path);
                $parseado = $svc->parsearContenido($texto);
                $res = $svc->importar(
                    parseado:        $parseado,
                    tenantId:        $tenant->id,
                    autorYo:         $a['autorYo'],
                    telefonoCliente: $tel,
                    nombreCliente:   $a['nombreCliente'],
                    connectionId:    $connId,
                    fuente:          'wa_export_txt_batch',
                );

                $this->archivos[$idx]['estado']    = 'importado';
                $this->archivos[$idx]['resultado'] = $res;
                $this->archivos[$idx]['error']     = '';
                $okCount++;

                // Liberar disco
                Storage::disk('local')->delete($path);
            } catch (\Throwable $e) {
                $this->archivos[$idx]['estado'] = 'error';
                $this->archivos[$idx]['error']  = $e->getMessage();
                $errCount++;
            }
        }

        $this->aviso = "✅ Importados: {$okCount} · ⚠️ Omitidos: {$skipCount} · ❌ Errores: {$errCount}";
        $this->dispatch('notify', ['type' => 'success', 'message' => $this->aviso]);
    }

    private function rutaTemp(string $uid): string
    {
        return "imports-wa/{$this->sesionId}/{$uid}.txt";
    }

    public function render()
    {
        $tenants = app(TenantManager::class)->withoutTenant(function () {
            return Tenant::where('activo', true)->orderBy('nombre')->get(['id','slug','nombre']);
        });

        // KPIs agregados
        $kpis = [
            'archivos'        => count($this->archivos),
            'mensajes_total'  => array_sum(array_column($this->archivos, 'total')),
            'listos'          => count(array_filter($this->archivos, fn ($a) => $a['telefono'] && $a['autorYo'] && $a['estado'] !== 'importado')),
            'importados'      => count(array_filter($this->archivos, fn ($a) => $a['estado'] === 'importado')),
        ];

        return view('livewire.admin.importar-historial-wa', [
            'tenants' => $tenants,
            'kpis'    => $kpis,
        ])->layout('layouts.app');
    }
}
