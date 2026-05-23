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
            'message' => "🔁 {$count} fallidos marcados para reintento.",
        ]);
    }

    public string $nombre  = '';
    public string $mensaje = '';
    public string $audienciaTipo = 'todos';
    public ?int   $zonaId = null;
    public ?int   $sedeId = null;
    public int    $minPedidos = 1;
    public string $telefonosManual = '';

    /** Archivo Excel/CSV temporal (.xlsx, .xls, .csv) */
    public $archivoExcel = null;
    public int $numerosImportados = 0;

    /** Imagen para envío masivo (Livewire temp file) */
    public $imagen = null;
    public ?string $mediaUrlExistente = null;

    public int    $intervaloMinSeg   = 8;
    public int    $intervaloMaxSeg   = 20;
    public int    $loteTamano        = 50;
    public int    $descansoLoteMin   = 30;
    public string $ventanaDesde      = '08:00';
    public string $ventanaHasta      = '20:00';

    public ?string $programadaPara = null;

    protected function rules(): array
    {
        return [
            'nombre'           => 'required|string|max:150',
            'mensaje'          => 'required|string|max:2000',
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

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✓ Importados {$this->numerosImportados} números. Audiencia cambiada a 'manual'.",
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
        $campanas = CampanaWhatsapp::orderByDesc('id')->paginate(15);
        $zonas    = ZonaCobertura::where('activa', true)->orderBy('nombre')->get();
        $sedes    = Sede::orderBy('nombre')->get();

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

                // Lista filtrada (últimos 50 con orden por actividad)
                if ($this->filtroMonitor !== 'todos') {
                    $destinatariosQuery->where('estado', $this->filtroMonitor);
                }
                $monitorDestinatarios = $destinatariosQuery
                    ->orderByRaw("FIELD(estado, 'pendiente', 'enviado', 'fallido', 'omitido')")
                    ->orderByDesc('enviado_at')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get();
            }
        }

        return view('livewire.campanas.index', compact(
            'campanas', 'zonas', 'sedes',
            'monitorCampana', 'monitorDestinatarios', 'monitorEstadisticas'
        ))->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'mensaje', 'zonaId', 'sedeId',
                      'telefonosManual', 'programadaPara', 'imagen',
                      'archivoExcel', 'numerosImportados', 'mediaUrlExistente']);
        $this->audienciaTipo   = 'todos';
        $this->minPedidos      = 1;
        $this->intervaloMinSeg = 8;
        $this->intervaloMaxSeg = 20;
        $this->loteTamano      = 50;
        $this->descansoLoteMin = 30;
        $this->ventanaDesde    = '08:00';
        $this->ventanaHasta    = '20:00';
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
        $this->minPedidos       = $f['min_pedidos']  ?? 1;
        $this->telefonosManual  = isset($f['telefonos']) ? implode("\n", $f['telefonos']) : '';
        $this->intervaloMinSeg  = $c->intervalo_min_seg;
        $this->intervaloMaxSeg  = $c->intervalo_max_seg;
        $this->loteTamano       = $c->lote_tamano;
        $this->descansoLoteMin  = $c->descanso_lote_min;
        $this->ventanaDesde     = substr($c->ventana_desde ?: '08:00:00', 0, 5);
        $this->ventanaHasta     = substr($c->ventana_hasta ?: '20:00:00', 0, 5);
        $this->programadaPara   = $c->programada_para?->format('Y-m-d\TH:i');
        $this->mediaUrlExistente= $c->media_url;
        $this->imagen           = null;
        $this->archivoExcel     = null;
        $this->numerosImportados= 0;
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $filtros = [];
        if ($this->audienciaTipo === 'zona' && $this->zonaId)         $filtros['zona_id'] = $this->zonaId;
        if ($this->audienciaTipo === 'sede' && $this->sedeId)         $filtros['sede_id'] = $this->sedeId;
        if ($this->audienciaTipo === 'con_pedidos')                   $filtros['min_pedidos'] = $this->minPedidos;
        if ($this->audienciaTipo === 'manual')                        $filtros['telefonos'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->telefonosManual))));

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

        $data = [
            'nombre'             => $this->nombre,
            'mensaje'            => $this->mensaje,
            'media_url'          => $mediaUrl,
            'audiencia_tipo'     => $this->audienciaTipo,
            'audiencia_filtros'  => $filtros,
            'intervalo_min_seg'  => $this->intervaloMinSeg,
            'intervalo_max_seg'  => max($this->intervaloMaxSeg, $this->intervaloMinSeg),
            'lote_tamano'        => $this->loteTamano,
            'descanso_lote_min'  => $this->descansoLoteMin,
            'ventana_desde'      => $this->ventanaDesde . ':00',
            'ventana_hasta'      => $this->ventanaHasta . ':00',
            'programada_para'    => $this->programadaPara ?: null,
            'estado'             => $this->programadaPara ? CampanaWhatsapp::ESTADO_PROGRAMADA : CampanaWhatsapp::ESTADO_BORRADOR,
            'creado_por'         => auth()->id(),
        ];

        if ($this->editandoId) {
            CampanaWhatsapp::findOrFail($this->editandoId)->update($data);
        } else {
            CampanaWhatsapp::create($data);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Campaña guardada']);
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

        if ($c->total_destinatarios === 0) {
            app(CampanaSenderService::class)->generarAudiencia($c);
            $c->refresh();
        }

        if ($c->total_destinatarios === 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sin destinatarios. Revisa la audiencia.']);
            return;
        }

        $c->update([
            'estado'      => CampanaWhatsapp::ESTADO_CORRIENDO,
            'iniciada_at' => $c->iniciada_at ?: now(),
        ]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '▶️ Campaña iniciada. Procesará en lotes según el cron.']);
    }

    public function pausar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_PAUSADA]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '⏸ Pausada']);
    }

    public function reanudar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CORRIENDO]);
        $this->dispatch('notify', ['type' => 'success', 'message' => '▶️ Reanudada']);
    }

    public function cancelar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->update(['estado' => CampanaWhatsapp::ESTADO_CANCELADA]);
        $this->dispatch('notify', ['type' => 'warning', 'message' => '✕ Cancelada']);
    }

    public function eliminar(int $id): void
    {
        CampanaWhatsapp::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Eliminada']);
    }
}
