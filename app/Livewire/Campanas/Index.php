<?php

namespace App\Livewire\Campanas;

use App\Models\CampanaWhatsapp;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use App\Services\CampanaSenderService;
use App\Services\WhatsappResolverService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    protected $paginationTheme = 'tailwind';

    // ─── Estado WhatsApp ────────────────────────────────────────────────
    public bool   $waConectado    = false;
    public string $waStatus       = 'UNKNOWN';
    public string $waPhone        = '';
    public string $waNombre       = '';
    public ?int   $waConnectionId = null;

    public bool $modal = false;
    public ?int $editandoId = null;

    /** 📡 Monitor en vivo */
    public ?int $monitoreoId = null;
    public string $filtroMonitor = 'todos'; // todos | enviado | fallido | pendiente

    public function verProgreso(int $id): void
    {
        $this->monitoreoId   = $id;
        $this->filtroMonitor = 'todos';
    }

    public function cerrarMonitor(): void
    {
        $this->monitoreoId = null;
    }

    public function reintentarFallidos(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);
        $count = \App\Models\CampanaDestinatario::where('campana_id', $c->id)
            ->where('estado', \App\Models\CampanaDestinatario::ESTADO_FALLIDO)
            ->update([
                'estado'        => \App\Models\CampanaDestinatario::ESTADO_PENDIENTE,
                'error_detalle' => null,
            ]);

        $c->update([
            'total_pendientes' => $c->destinatarios()->where('estado', \App\Models\CampanaDestinatario::ESTADO_PENDIENTE)->count(),
            'total_fallidos'   => $c->destinatarios()->where('estado', \App\Models\CampanaDestinatario::ESTADO_FALLIDO)->count(),
            'estado'           => $count > 0 ? CampanaWhatsapp::ESTADO_CORRIENDO : $c->estado,
        ]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "{$count} fallidos marcados para reintento.",
        ]);
    }

    public string $nombre  = '';
    public string $mensaje = '';

    // 🟢 Para tenants Meta: en lugar de texto libre, se envía plantilla
    public ?int   $plantillaMetaId  = null;
    public array  $plantillaVariables = []; // posicional: ['1'=>'Cliente', '2'=>'30%']

    public string $audienciaTipo = 'todos';
    public ?int   $zonaId = null;
    public ?int   $sedeId = null;
    public ?int   $grupoClienteId = null; // 👥 audiencia por grupo
    public int    $minPedidos = 1;
    public string $telefonosManual = '';

    /** Archivo Excel/CSV temporal (.xlsx, .xls, .csv) */
    public $archivoExcel = null;
    public int $numerosImportados = 0;
    /** Cantidad de nombres capturados del Excel (el mapa real va en sesión, no como
     *  propiedad pública: un arreglo grande rompería el payload de Livewire). */
    public int $nombresImportadosCount = 0;
    private const SESION_NOMBRES = 'campana_nombres_importados';

    /** Imagen para envío masivo (Livewire temp file) */
    public $imagen = null;
    public ?string $mediaUrlExistente = null;

    public int    $intervaloMinSeg   = 8;
    public int    $intervaloMaxSeg   = 20;
    public int    $loteTamano        = 50;
    public int    $descansoLoteMin   = 30;
    // ⏰ Por defecto 24/7 (cualquier hora). El usuario puede restringir si quiere.
    public string $ventanaDesde      = '00:00';
    public string $ventanaHasta      = '23:59';

    public ?string $programadaPara = null;

    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:150',
            'mensaje'          => 'nullable|string|max:2000', // opcional si se usa plantilla
            'plantillaMetaId'  => 'nullable|integer|exists:meta_whatsapp_plantillas,id',
            'plantillaVariables' => 'array',
            'audienciaTipo'    => 'required|in:todos,zona,sede,con_pedidos,sin_pedidos,manual',
            'intervaloMinSeg'  => 'integer|min:1|max:300',
            'intervaloMaxSeg'  => 'integer|min:1|max:600',
            'loteTamano'       => 'integer|min:1|max:500',
            'descansoLoteMin'  => 'integer|min:0|max:1440',
            'ventanaDesde'     => 'string',
            'ventanaHasta'     => 'string',
            'imagen'           => 'nullable|image|max:20480', // 20 MB
            'archivoExcel'     => 'nullable|file|mimes:xlsx,xls,csv,txt|max:5120', // 5 MB
        ];
    }

    /**
     * Cuando suben un Excel/CSV, lo parsea con PhpSpreadsheet y mete los
     * teléfonos en el textarea manual. Detecta columna 'telefono', 'phone',
     * 'celular' o usa la primera columna numérica.
     */
    public function updatedArchivoExcel(): void
    {
        $this->validate(['archivoExcel' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:5120']);

        if (!$this->archivoExcel) return;

        try {
            $path = $this->archivoExcel->getRealPath();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            if (empty($rows)) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'El archivo está vacío.']);
                return;
            }

            // Detectar columna de teléfono mirando la primera fila como header
            $header = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $rows[0]);
            $colTel = null;
            $colNom = null;
            foreach ($header as $idx => $h) {
                if ($colTel === null && preg_match('/(tel|cel|whats|phone|movil|m[oó]vil)/u', $h)) $colTel = $idx;
                if ($colNom === null && preg_match('/(nombre|name|cliente)/u', $h)) $colNom = $idx;
            }

            // Si no hay header reconocible, asumimos col 0 = telefono
            $skipFirst = ($colTel !== null);
            if ($colTel === null) $colTel = 0;

            $telefonos = [];
            $nombres   = [];
            foreach ($rows as $i => $row) {
                if ($skipFirst && $i === 0) continue;
                $rawTel = (string) ($row[$colTel] ?? '');
                $tel = preg_replace('/\D+/', '', $rawTel);
                if (mb_strlen($tel) < 7) continue;

                // Anteponer 57 si parece celular CO sin código país (10 dígitos que empiezan en 3)
                if (mb_strlen($tel) === 10 && str_starts_with($tel, '3')) $tel = '57' . $tel;

                $telefonos[$tel] = $tel;
                if ($colNom !== null) {
                    $nombres[$tel] = trim((string) ($row[$colNom] ?? ''));
                }
            }

            $this->telefonosManual = implode("\n", array_values($telefonos));
            $this->audienciaTipo = 'manual';
            $this->numerosImportados = count($telefonos);
            // Guardar el nombre capturado por teléfono (solo los que traen nombre).
            // Va en SESIÓN, no en una propiedad pública: con miles de filas un
            // arreglo gigante rompería el payload de Livewire al guardar.
            $nombresLimpios = array_filter($nombres, fn ($n) => trim((string) $n) !== '');
            session([self::SESION_NOMBRES => $nombresLimpios]);
            $this->nombresImportadosCount = count($nombresLimpios);

            $conNombre = $this->nombresImportadosCount;
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "Importados {$this->numerosImportados} números"
                    . ($conNombre ? " ({$conNombre} con nombre)" : '')
                    . ". Audiencia cambiada a 'manual'.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No pude leer el archivo: ' . $e->getMessage(),
            ]);
        }
    }

    public function mount(): void
    {
        $this->refreshEstadoWa();

        // 👥 Si vino desde "Difundir" de un grupo, preseleccionar audiencia=grupo
        //    y abrir el formulario de nueva campaña.
        $grupoId = request()->integer('grupo_id');
        if ($grupoId) {
            $this->audienciaTipo  = 'grupo';
            $this->grupoClienteId = $grupoId;
            $this->modal = true;
        }
    }

    /** Consulta el estado de la sesión WA del tenant. Llamado en mount + wire:poll.30s */
    public function refreshEstadoWa(): void
    {
        try {
            $resolver = app(WhatsappResolverService::class);
            $cred     = $resolver->credenciales();
            $base     = rtrim($cred['api_base_url'] ?? '', '/');
            if (!$base) return;

            $token = $resolver->token();
            if (!$token) return;

            $resp = Http::withoutVerifying()->withToken($token)->timeout(8)->get("{$base}/whatsapp/");
            if (!$resp->successful()) return;

            $whatsapps = collect($resp->json('whatsapps', []));
            if ($whatsapps->isEmpty()) return;

            // Priorizar el connection_id del tenant
            $ids  = $resolver->connectionIdsDelTenant();
            $conn = !empty($ids) ? $whatsapps->firstWhere('id', $ids[0]) : null;
            $conn = $conn ?? $whatsapps->first(fn ($w) => strtoupper($w['status'] ?? '') === 'CONNECTED')
                         ?? $whatsapps->first();

            if (!$conn) return;

            $this->waStatus       = strtoupper($conn['status'] ?? 'UNKNOWN');
            $this->waConectado    = $this->waStatus === 'CONNECTED';
            $this->waPhone        = $conn['phoneNumber'] ?? $conn['profileName'] ?? $conn['name'] ?? '';
            $this->waNombre       = $conn['profileName'] ?? $conn['name'] ?? '';
            $this->waConnectionId = isset($conn['id']) ? (int) $conn['id'] : null;
        } catch (\Throwable) {
            // Falla silenciosamente — mantiene el estado anterior
        }
    }

    public function render()
    {
        // Excluir 'audiencia_filtros' (puede ser un JSON enorme en envíos masivos):
        // incluirlo en el ORDER BY hace que MySQL agote el sort buffer (error 1038).
        $colsLista = array_values(array_diff(
            \Illuminate\Support\Facades\Schema::getColumnListing('campanas_whatsapp'),
            ['audiencia_filtros']
        ));
        $campanas = CampanaWhatsapp::orderByDesc('id')->paginate(15, $colsLista);
        $zonas    = ZonaCobertura::where('activa', true)->orderBy('nombre')->get();
        $sedes    = Sede::orderBy('nombre')->get();

        // 📋 Cola de jobs pendientes (en cola 'campanas')
        $colaJobs = collect();
        $failedJobs = collect();
        try {
            $tenantActual = app(\App\Services\TenantManager::class)->current();
            $campanasIds = $tenantActual
                ? CampanaWhatsapp::where('tenant_id', $tenantActual->id)->pluck('id')->toArray()
                : [];

            // Jobs pendientes en cola
            $rawJobs = \Illuminate\Support\Facades\DB::table('jobs')
                ->where('queue', 'campanas')
                ->orderBy('available_at')
                ->limit(20)
                ->get(['id', 'queue', 'payload', 'attempts', 'available_at', 'created_at']);

            $colaJobs = $rawJobs->map(function ($j) use ($campanasIds) {
                $payload = json_decode($j->payload, true);
                $cmdSerialized = $payload['data']['command'] ?? '';
                // Extraer campanaId del payload (objeto serializado de PHP)
                $campanaId = null;
                if (preg_match('/campanaId";i:(\d+)/', $cmdSerialized, $m)) {
                    $campanaId = (int) $m[1];
                }
                $campana = $campanaId ? CampanaWhatsapp::find($campanaId) : null;
                $isMyTenant = $campanaId && in_array($campanaId, $campanasIds);

                return (object) [
                    'id'           => $j->id,
                    'campana_id'   => $campanaId,
                    'campana'      => $campana,
                    'is_mine'      => $isMyTenant,
                    'attempts'     => $j->attempts,
                    'available_at' => \Carbon\Carbon::createFromTimestamp($j->available_at),
                    'created_at'   => \Carbon\Carbon::createFromTimestamp($j->created_at),
                ];
            })->filter(fn ($j) => $j->is_mine);

            // Jobs fallidos del tenant
            $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('queue', 'campanas')
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get(['id', 'payload', 'exception', 'failed_at'])
                ->map(function ($j) use ($campanasIds) {
                    $payload = json_decode($j->payload, true);
                    $cmdSerialized = $payload['data']['command'] ?? '';
                    $campanaId = null;
                    if (preg_match('/campanaId";i:(\d+)/', $cmdSerialized, $m)) {
                        $campanaId = (int) $m[1];
                    }
                    return (object) [
                        'id'         => $j->id,
                        'campana_id' => $campanaId,
                        'campana'    => $campanaId ? CampanaWhatsapp::find($campanaId) : null,
                        'is_mine'    => $campanaId && in_array($campanaId, $campanasIds),
                        'error'      => mb_substr(strtok($j->exception, "\n"), 0, 200),
                        'failed_at'  => \Carbon\Carbon::parse($j->failed_at),
                    ];
                })
                ->filter(fn ($j) => $j->is_mine);
        } catch (\Throwable $e) {
            // Si falla, no rompe la página
        }

        // 📡 Monitor en vivo
        $monitorCampana = null;
        $monitorDestinatarios = collect();
        $monitorEstadisticas = ['enviado' => 0, 'fallido' => 0, 'pendiente' => 0, 'total' => 0, 'pct' => 0];

        if ($this->monitoreoId) {
            $monitorCampana = CampanaWhatsapp::find($this->monitoreoId);
            if ($monitorCampana) {
                $destinatariosQuery = \App\Models\CampanaDestinatario::where('campana_id', $monitorCampana->id);

                // Conteos
                $contadores = (clone $destinatariosQuery)
                    ->selectRaw('estado, COUNT(*) as cnt')
                    ->groupBy('estado')
                    ->pluck('cnt', 'estado')
                    ->toArray();

                $monitorEstadisticas['enviado']   = (int) ($contadores['enviado'] ?? 0);
                $monitorEstadisticas['fallido']   = (int) ($contadores['fallido'] ?? 0);
                $monitorEstadisticas['pendiente'] = (int) ($contadores['pendiente'] ?? 0);
                $monitorEstadisticas['omitido']   = (int) ($contadores['omitido'] ?? 0);
                $monitorEstadisticas['total']     = array_sum($monitorEstadisticas) - $monitorEstadisticas['total'];
                $monitorEstadisticas['pct']       = $monitorEstadisticas['total'] > 0
                    ? (int) round((($monitorEstadisticas['enviado'] + $monitorEstadisticas['fallido']) / $monitorEstadisticas['total']) * 100)
                    : 0;

                // 📩 Respondieron: contestaron al mensaje (certeza 100%)
                $monitorEstadisticas['respondieron'] = (int) \App\Models\CampanaDestinatario::where('campana_id', $monitorCampana->id)
                    ->whereNotNull('respondio_at')
                    ->count();

                // 👁️ Leyeron: ACK >= 3 en algún mensaje saliente al teléfono después del envío
                // O respondieron (si respondieron, obvio leyeron)
                $telefonosEnviados = \App\Models\CampanaDestinatario::where('campana_id', $monitorCampana->id)
                    ->where('estado', 'enviado')
                    ->pluck('enviado_at', 'telefono');

                $leyeron = 0;
                $entregados = 0;
                foreach ($telefonosEnviados as $telefono => $enviadoAt) {
                    try {
                        $maxAck = \App\Models\MensajeWhatsapp::where('rol', 'assistant')
                            ->whereHas('conversacion.cliente', fn ($q) => $q->where('telefono_normalizado', $telefono))
                            ->where('created_at', '>=', \Carbon\Carbon::parse($enviadoAt)->subMinutes(2))
                            ->where('created_at', '<=', \Carbon\Carbon::parse($enviadoAt)->addHours(24))
                            ->max('ack');
                        if ($maxAck >= 3) $leyeron++;
                        if ($maxAck >= 2) $entregados++;
                    } catch (\Throwable $e) { /* skip */ }
                }
                // Si respondieron, también cuentan como leyeron (lo cual implica que también entregaron)
                $monitorEstadisticas['leyeron']    = max($leyeron, $monitorEstadisticas['respondieron']);
                $monitorEstadisticas['entregados'] = max($entregados, $monitorEstadisticas['leyeron']);

                $monitorEstadisticas['tasa_respuesta'] = $monitorEstadisticas['enviado'] > 0
                    ? round(($monitorEstadisticas['respondieron'] / $monitorEstadisticas['enviado']) * 100, 1)
                    : 0;
                $monitorEstadisticas['tasa_lectura'] = $monitorEstadisticas['enviado'] > 0
                    ? round(($monitorEstadisticas['leyeron'] / $monitorEstadisticas['enviado']) * 100, 1)
                    : 0;

                // Lista filtrada (últimos 100 con orden por actividad)
                if ($this->filtroMonitor !== 'todos') {
                    $destinatariosQuery->where('estado', $this->filtroMonitor);
                }
                $monitorDestinatarios = $destinatariosQuery
                    ->orderByRaw("FIELD(estado, 'pendiente', 'enviado', 'fallido', 'omitido')")
                    ->orderByDesc('enviado_at')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get();

                // 🔍 Enriquecer cada destinatario con su ACK máximo (entregado/leído)
                foreach ($monitorDestinatarios as $d) {
                    if (!$d->enviado_at) {
                        $d->ack_max = null;
                        continue;
                    }
                    try {
                        $d->ack_max = (int) \App\Models\MensajeWhatsapp::where('rol', 'assistant')
                            ->whereHas('conversacion.cliente', fn ($q) => $q->where('telefono_normalizado', $d->telefono))
                            ->where('created_at', '>=', \Carbon\Carbon::parse($d->enviado_at)->subMinutes(2))
                            ->where('created_at', '<=', \Carbon\Carbon::parse($d->enviado_at)->addHours(24))
                            ->max('ack');
                    } catch (\Throwable $e) {
                        $d->ack_max = null;
                    }
                }
            }
        }

        // 🟢 Provider del tenant + plantillas Meta aprobadas (para UI)
        $tenant = app(\App\Services\TenantManager::class)->current();
        $providerMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;
        $plantillasMeta = $providerMeta
            ? \App\Models\MetaWhatsappPlantilla::where('activa', true)
                ->where('estado', 'aprobada')   // BD: aprobada/rechazada/pendiente
                ->where('categoria', 'MARKETING') // solo MARKETING para campañas masivas
                ->orderBy('nombre')
                ->get()
            : collect();

        // Si hay plantilla seleccionada, calcular cuántas variables tiene
        $plantillaSeleccionada = $this->plantillaMetaId
            ? \App\Models\MetaWhatsappPlantilla::find($this->plantillaMetaId)
            : null;

        $grupos = \App\Models\GrupoCliente::withCount('clientes')->orderBy('nombre')->get();

        return view('livewire.campanas.index', compact(
            'campanas', 'zonas', 'sedes', 'grupos',
            'monitorCampana', 'monitorDestinatarios', 'monitorEstadisticas',
            'colaJobs', 'failedJobs',
            'providerMeta', 'plantillasMeta', 'plantillaSeleccionada'
        ))->layout('layouts.app');
    }

    /** Reintentar un job fallido */
    public function reintentarJobFallido(int $failedJobId): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => [$failedJobId]]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Job re-encolado para reintento']);
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /** Borrar un job fallido */
    public function borrarJobFallido(int $failedJobId): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('failed_jobs')->where('id', $failedJobId)->delete();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Job fallido eliminado']);
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'mensaje', 'zonaId', 'sedeId',
                      'telefonosManual', 'programadaPara', 'imagen',
                      'archivoExcel', 'numerosImportados', 'nombresImportadosCount', 'mediaUrlExistente',
                      'plantillaMetaId', 'plantillaVariables']);
        session()->forget(self::SESION_NOMBRES);
        $this->audienciaTipo   = 'todos';
        $this->minPedidos      = 1;
        $this->intervaloMinSeg = 8;
        $this->intervaloMaxSeg = 20;
        $this->loteTamano      = 50;
        $this->descansoLoteMin = 30;
        // Por defecto 24/7 — puede enviarse a cualquier hora
        $this->ventanaDesde    = '00:00';
        $this->ventanaHasta    = '23:59';
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);
        $this->editandoId       = $c->id;
        $this->nombre           = $c->nombre;
        $this->mensaje          = $c->mensaje;
        $this->audienciaTipo    = $c->audiencia_tipo;
        $f = $c->audiencia_filtros ?? [];
        $this->zonaId           = $f['zona_id']      ?? null;
        $this->sedeId           = $f['sede_id']      ?? null;
        $this->grupoClienteId   = $f['grupo_id']     ?? null;
        $this->minPedidos       = $f['min_pedidos']  ?? 1;
        $this->telefonosManual  = isset($f['telefonos']) ? implode("\n", $f['telefonos']) : '';
        // Los nombres ya guardados se conservan desde audiencia_filtros al guardar;
        // limpiamos la sesión para no mezclar con un Excel anterior.
        session()->forget(self::SESION_NOMBRES);
        $this->nombresImportadosCount = count($f['nombres'] ?? []);
        $this->intervaloMinSeg  = $c->intervalo_min_seg;
        $this->intervaloMaxSeg  = $c->intervalo_max_seg;
        $this->loteTamano       = $c->lote_tamano;
        $this->descansoLoteMin  = $c->descanso_lote_min;
        $this->ventanaDesde     = substr($c->ventana_desde ?: '00:00:00', 0, 5);
        $this->ventanaHasta     = substr($c->ventana_hasta ?: '23:59:00', 0, 5);
        $this->programadaPara   = $c->programada_para?->format('Y-m-d\TH:i');
        $this->mediaUrlExistente= $c->media_url;
        $this->imagen           = null;
        $this->archivoExcel     = null;
        $this->numerosImportados= 0;
        // 🟢 Plantilla Meta si la campaña la usa
        if ($c->plantilla_meta_nombre) {
            $tpl = \App\Models\MetaWhatsappPlantilla::where('nombre', $c->plantilla_meta_nombre)
                ->where('idioma', $c->plantilla_meta_idioma ?: 'es')
                ->first();
            $this->plantillaMetaId = $tpl?->id;
            $this->plantillaVariables = is_array($c->plantilla_meta_variables)
                ? $c->plantilla_meta_variables
                : [];
        } else {
            $this->plantillaMetaId = null;
            $this->plantillaVariables = [];
        }
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        try {
            $this->validate([], [], [
                'nombre'        => 'Nombre de la campaña',
                'audienciaTipo' => 'Audiencia',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $primero = collect($e->errors())->flatten()->first() ?: 'Revisa los campos marcados.';
            // Mensaje claro si falta el nombre (el error más común, arriba del formulario)
            if (array_key_exists('nombre', $e->errors())) {
                $primero = '✋ Falta el "Nombre de la campaña" (campo obligatorio, arriba del formulario).';
            }
            $this->dispatch('notify', ['type' => 'error', 'message' => $primero]);
            throw $e;
        }

        $filtros = [];
        if ($this->audienciaTipo === 'zona' && $this->zonaId)         $filtros['zona_id'] = $this->zonaId;
        if ($this->audienciaTipo === 'sede' && $this->sedeId)         $filtros['sede_id'] = $this->sedeId;
        if ($this->audienciaTipo === 'grupo' && $this->grupoClienteId) $filtros['grupo_id'] = $this->grupoClienteId;
        if ($this->audienciaTipo === 'con_pedidos')                   $filtros['min_pedidos'] = $this->minPedidos;
        if ($this->audienciaTipo === 'manual') {
            $filtros['telefonos'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->telefonosManual))));
            // 👤 Nombres capturados del Excel (tel normalizado => nombre), desde sesión.
            $nombres = session(self::SESION_NOMBRES, []);
            // Si no se resubió Excel pero se está editando, conservar los ya guardados.
            if (empty($nombres) && $this->editandoId) {
                $prev = CampanaWhatsapp::find($this->editandoId);
                $nombres = $prev->audiencia_filtros['nombres'] ?? [];
            }
            if (!empty($nombres)) {
                $filtros['nombres'] = $nombres;
            }
        }

        // Subir imagen al disco público si vino una nueva
        // ✅ Aislamiento por tenant: cada empresa tiene su propia carpeta
        // tenants/{slug}/campanas/ para que las imágenes no se mezclen
        // entre tenants y sean fáciles de auditar/limpiar por tenant.
        $mediaUrl = $this->mediaUrlExistente;
        if ($this->imagen) {
            $tenant = app(\App\Services\TenantManager::class)->current();
            $slug = $tenant?->slug ?: 'sin-tenant';
            $carpeta = "tenants/{$slug}/campanas";
            $path = $this->imagen->store($carpeta, 'public');
            $mediaUrl = Storage::disk('public')->url($path);
        }

        // 🟢 Si hay plantilla seleccionada, resolver nombre/idioma y guardar vars
        $plantillaNombre = null;
        $plantillaIdioma = null;
        $plantillaVars   = null;
        if ($this->plantillaMetaId) {
            $tpl = \App\Models\MetaWhatsappPlantilla::find($this->plantillaMetaId);
            if ($tpl) {
                $plantillaNombre = $tpl->nombre;
                $plantillaIdioma = $tpl->idioma ?: 'es';
                // Filtrar y mantener solo las claves numéricas relevantes
                $plantillaVars = array_filter($this->plantillaVariables, fn($v) => $v !== null && $v !== '');
            }
        }

        $data = [
            'nombre'             => $this->nombre,
            'mensaje'            => $this->mensaje,
            'media_url'          => $mediaUrl,
            'plantilla_meta_nombre'    => $plantillaNombre,
            'plantilla_meta_idioma'    => $plantillaIdioma,
            'plantilla_meta_variables' => $plantillaVars,
            'audiencia_tipo'     => $this->audienciaTipo,
            'audiencia_filtros'  => $filtros,
            'intervalo_min_seg'  => $this->intervaloMinSeg,
            'intervalo_max_seg'  => max($this->intervaloMaxSeg, $this->intervaloMinSeg),
            'lote_tamano'        => $this->loteTamano,
            'descanso_lote_min'  => $this->descansoLoteMin,
            'ventana_desde'      => $this->ventanaDesde . ':00',
            'ventana_hasta'      => $this->ventanaHasta . ':00',
            'programada_para'    => $this->programadaPara ?: null,
            // 🛡️ Estado automático SEGURO (nunca envía sin acción explícita):
            //   - Con fecha programada → "programada" (cron la activa en su hora)
            //   - Sin fecha programada → "borrador" (espera click manual en "Iniciar")
            //
            // ⚠️ El comportamiento previo era enviar YA sin fecha, lo cual causaba
            // envíos accidentales al guardar. Ahora SIEMPRE requiere acción
            // explícita: o programación, o click manual.
            'estado'             => $this->programadaPara
                ? CampanaWhatsapp::ESTADO_PROGRAMADA
                : CampanaWhatsapp::ESTADO_BORRADOR,
            'iniciada_at'        => null,  // se llena cuando arranque (cron o click)
            'creado_por'         => auth()->id(),
        ];

        $esNueva = !$this->editandoId;
        if ($this->editandoId) {
            $c = CampanaWhatsapp::findOrFail($this->editandoId);
            $c->update($data);
        } else {
            $c = CampanaWhatsapp::create($data);
        }

        // 🛡️ AUDIENCIA — REGLA CRÍTICA contra borrado accidental:
        //
        //   - Campaña NUEVA → siempre generar audiencia
        //   - Campaña EXISTENTE sin envíos previos → puede regenerar (no se pierde nada)
        //   - Campaña EXISTENTE CON envíos previos → NO regenerar (preserva los
        //     que ya recibieron + los pendientes; sino el doble-envío al
        //     reactivar dispara baneos de WhatsApp)
        //
        // Si necesitas RE-generar audiencia explícitamente, usa el botón
        // "Generar audiencia" en la lista (método generarAudiencia()).
        try {
            $tieneEnviosPrevios = $c->destinatarios()
                ->whereIn('estado', ['enviado', 'fallido'])
                ->exists();

            if ($esNueva || !$tieneEnviosPrevios) {
                app(CampanaSenderService::class)->generarAudiencia($c);
                $c->refresh();
            } else {
                \Illuminate\Support\Facades\Log::info('🛡️ Campaña editada con envíos previos — audiencia PRESERVADA', [
                    'campana_id' => $c->id,
                    'enviados'   => $c->total_enviados,
                    'pendientes' => $c->total_pendientes,
                ]);
            }
        } catch (\Throwable $e) {
            // Si falla, no bloquear el guardado
            \Illuminate\Support\Facades\Log::warning('Generar audiencia falló: ' . $e->getMessage());
        }

        // 🚀 DISPATCH del job SOLO si está programada con fecha. Sin fecha
        // queda en BORRADOR esperando click manual en "Iniciar" — el método
        // iniciar() de este componente hace el dispatch en ese momento.
        if ($this->programadaPara) {
            try {
                \App\Jobs\ProcesarCampanaJob::dispatch($c->id)
                    ->delay(\Carbon\Carbon::parse($this->programadaPara));
            } catch (\Throwable $e) {
                // Si falla el dispatch, el cron campanas:procesar es la red de
                // seguridad: rescata las "programada" cuya hora ya pasó.
                \Illuminate\Support\Facades\Log::warning('No se pudo despachar ProcesarCampanaJob: ' . $e->getMessage());
            }
        }

        $this->modal = false;

        // Mensaje contextual según lo que pasó
        if ($this->programadaPara) {
            $msg = "✅ Campaña programada para el " . \Carbon\Carbon::parse($this->programadaPara)->format('d/m/Y H:i') . " ({$c->total_destinatarios} destinatarios). Se enviará automáticamente a esa hora.";
            $type = 'success';
        } elseif ($c->total_destinatarios === 0) {
            $msg = '📝 Campaña guardada como BORRADOR pero sin destinatarios. Revisa la audiencia y luego haz clic en "Iniciar".';
            $type = 'warning';
        } else {
            $msg = "📝 Campaña guardada como BORRADOR con {$c->total_destinatarios} destinatarios. Haz clic en \"Iniciar\" cuando quieras enviarla.";
            $type = 'success';
        }

        $this->dispatch('notify', ['type' => $type, 'message' => $msg]);
    }

    public function generarAudiencia(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);
        $count = app(CampanaSenderService::class)->generarAudiencia($c);
        $this->dispatch('notify', ['type' => 'success', 'message' => "✓ {$count} destinatarios generados."]);
    }

    public function iniciar(int $id): void
    {
        $c = CampanaWhatsapp::findOrFail($id);

        // 🛡️ AUDIENCIA — REGLA CRÍTICA igual que en guardar():
        //   - Sin envíos previos → puede regenerar (no se pierde nada)
        //   - CON envíos previos → preservar (sino doble-envío → baneo de WA)
        //
        // Si necesitas re-generar, hay un botón "Generar audiencia" separado.
        $tieneEnviosPrevios = $c->destinatarios()
            ->whereIn('estado', ['enviado', 'fallido'])
            ->exists();

        if (!$tieneEnviosPrevios) {
            app(CampanaSenderService::class)->generarAudiencia($c);
            $c->refresh();
        } else {
            \Illuminate\Support\Facades\Log::info('🛡️ iniciar(): audiencia PRESERVADA — hay envíos previos', [
                'campana_id' => $c->id,
                'enviados'   => $c->total_enviados,
                'pendientes' => $c->total_pendientes,
            ]);
        }

        if ($c->total_destinatarios === 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sin destinatarios. Verifica que los teléfonos sean válidos o que haya clientes activos.',
            ]);
            return;
        }

        // ⚠️ Avisar si la hora actual está fuera de la ventana configurada
        if (!$c->enHorario()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "Campaña iniciada pero estás fuera de la ventana horaria ({$c->ventana_desde} - {$c->ventana_hasta}). Se enviará mañana cuando vuelva a estar dentro del horario.",
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Campaña iniciada con {$c->total_destinatarios} destinatario(s). Procesará en el próximo lote (max 1 min).",
            ]);
        }

        $c->update([
            'estado'      => CampanaWhatsapp::ESTADO_CORRIENDO,
            'iniciada_at' => $c->iniciada_at ?: now(),
        ]);

        // 🚀 DISPATCH inmediato del job — sin esperar al cron (max 1 min).
        // Si esto falla, el cron campanas:procesar es la red de seguridad.
        try {
            \App\Jobs\ProcesarCampanaJob::dispatch($c->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('iniciar(): no se pudo despachar ProcesarCampanaJob: ' . $e->getMessage());
        }
    }

    public function pausar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_PAUSADA]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Campaña pausada']);
    }

    public function reanudar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CORRIENDO]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Campaña reanudada']);
    }

    public function cancelar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CANCELADA]);
        $this->dispatch('notify', ['type' => 'warning', 'message' => 'Campaña cancelada']);
    }

    public function eliminar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Campaña eliminada']);
    }
}
