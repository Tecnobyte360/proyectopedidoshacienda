<?php

namespace App\Livewire\Admin\Facturacion;

use App\Facturacion\Models\FeConfiguracion;
use App\Facturacion\Models\FeResolucion;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Panel Super-Admin: habilitar y configurar EMISORES de facturación electrónica.
 * Cada emisor es un tenant con su identidad fiscal + credenciales DIAN + certificado.
 * (Fase 1: alta de datos y API key. El motor de emisión DIAN llega en fase 2.)
 */
class Index extends Component
{
    use WithFileUploads;

    // ── Modal Emisor ──────────────────────────────────────────
    public bool $modalEmisor = false;
    public ?int $configId    = null;
    public ?int $tenantId    = null;

    public string $nit           = '';
    public string $dv            = '';
    public string $razon_social  = '';
    public string $tipo_persona  = 'juridica';        // juridica | natural
    public string $municipio_codigo = '';             // DANE (ej. 05001 Medellín)
    public string $responsabilidades_text = '';       // una por línea (ej. O-13, O-15, R-99-PN)

    public string $ambiente      = 'habilitacion';    // habilitacion | produccion
    public string $software_id   = '';
    public string $software_pin  = '';
    public string $test_set_id   = '';

    /** @var mixed */
    public $certificado;                               // upload .p12/.pfx
    public string $certificado_password = '';
    public ?string $certificado_vence_at = null;
    public bool $activa = true;

    // ── Modal Resolución ──────────────────────────────────────
    public bool $modalResolucion = false;
    public ?int $resolucionId    = null;
    public ?int $resTenantId     = null;
    public string $res_tipo_documento = 'factura';
    public string $res_prefijo   = '';
    public ?int $res_numero_desde = null;
    public ?int $res_numero_hasta = null;
    public ?int $res_numero_actual = null;
    public string $res_numero_resolucion = '';
    public ?string $res_fecha_desde = null;
    public ?string $res_fecha_hasta = null;
    public string $res_clave_tecnica = '';
    public bool $res_activa = true;

    // ── Alta emisor ───────────────────────────────────────────
    public function nuevoEmisor(): void
    {
        $this->resetEmisor();
        $this->modalEmisor = true;
    }

    public function editarEmisor(int $configId): void
    {
        $c = FeConfiguracion::findOrFail($configId);
        $this->configId              = $c->id;
        $this->tenantId              = $c->tenant_id;
        $this->nit                   = (string) $c->nit;
        $this->dv                    = (string) $c->dv;
        $this->razon_social          = (string) $c->razon_social;
        $this->tipo_persona          = $c->tipo_persona ?: 'juridica';
        $this->municipio_codigo      = (string) $c->municipio_codigo;
        $this->responsabilidades_text = collect($c->responsabilidades_fiscales ?? [])->implode("\n");
        $this->ambiente              = $c->ambiente ?: 'habilitacion';
        $this->software_id           = (string) $c->software_id;
        $this->software_pin          = '';   // no se re-muestra el PIN cifrado
        $this->test_set_id           = (string) $c->test_set_id;
        $this->certificado_password  = '';
        $this->certificado_vence_at  = optional($c->certificado_vence_at)->format('Y-m-d');
        $this->activa                = (bool) $c->activa;
        $this->certificado           = null;
        $this->resetValidation();
        $this->modalEmisor = true;
    }

    protected function rulesEmisor(): array
    {
        return [
            'tenantId'          => 'required|integer|exists:tenants,id',
            'nit'               => 'required|string|max:20',
            'dv'                => 'nullable|string|max:2',
            'razon_social'      => 'required|string|max:200',
            'tipo_persona'      => 'required|in:juridica,natural',
            'municipio_codigo'  => 'nullable|string|max:10',
            'ambiente'          => 'required|in:habilitacion,produccion',
            'software_id'       => 'nullable|string|max:100',
            'software_pin'      => 'nullable|string|max:100',
            'test_set_id'       => 'nullable|string|max:100',
            'certificado'       => 'nullable|file|max:10240', // 10 MB
            'certificado_password' => 'nullable|string|max:200',
            'certificado_vence_at' => 'nullable|date',
            'activa'            => 'boolean',
        ];
    }

    public function guardarEmisor(): void
    {
        $this->validate($this->rulesEmisor());

        // Un tenant no puede tener dos configuraciones.
        $dup = FeConfiguracion::where('tenant_id', $this->tenantId)
            ->when($this->configId, fn ($q) => $q->where('id', '!=', $this->configId))
            ->exists();
        if ($dup) {
            $this->addError('tenantId', 'Ese tenant ya tiene una configuración de facturación.');
            return;
        }

        $data = [
            'tenant_id'                  => $this->tenantId,
            'nit'                        => trim($this->nit),
            'dv'                         => trim($this->dv),
            'razon_social'               => trim($this->razon_social),
            'tipo_persona'               => $this->tipo_persona,
            'municipio_codigo'           => trim($this->municipio_codigo) ?: null,
            'responsabilidades_fiscales' => collect(preg_split('/\r\n|\r|\n/', $this->responsabilidades_text))
                ->map(fn ($l) => trim($l))->filter()->values()->all(),
            'ambiente'                   => $this->ambiente,
            'software_id'                => trim($this->software_id) ?: null,
            'test_set_id'                => trim($this->test_set_id) ?: null,
            'certificado_vence_at'       => $this->certificado_vence_at ?: null,
            'activa'                     => $this->activa,
        ];

        // Solo sobreescribir secretos si el usuario los diligenció.
        if (trim($this->software_pin) !== '') {
            $data['software_pin'] = trim($this->software_pin);
        }
        if (trim($this->certificado_password) !== '') {
            $data['certificado_password'] = trim($this->certificado_password);
        }

        // Certificado: se guarda FUERA del webroot (storage/app/private/fe_certs).
        if ($this->certificado) {
            $nombre = 'fe_certs/tenant_' . $this->tenantId . '_' . Str::random(8) . '.'
                . strtolower($this->certificado->getClientOriginalExtension() ?: 'p12');
            Storage::disk('local')->putFileAs('', $this->certificado, $nombre);
            $data['certificado_path'] = $nombre;
        }

        if ($this->configId) {
            FeConfiguracion::whereKey($this->configId)->update($data);
        } else {
            $data['api_key'] = $this->generarApiKey();
            FeConfiguracion::create($data);
        }

        $this->modalEmisor = false;
        $this->resetEmisor();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Emisor guardado.']);
    }

    public function regenerarApiKey(int $configId): void
    {
        FeConfiguracion::whereKey($configId)->update(['api_key' => $this->generarApiKey()]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'API key regenerada.']);
    }

    public function toggleActiva(int $configId): void
    {
        $c = FeConfiguracion::find($configId);
        if (!$c) return;
        $c->activa = !$c->activa;
        $c->save();
    }

    // ── Resoluciones ──────────────────────────────────────────
    public function nuevaResolucion(int $tenantId): void
    {
        $this->resetResolucion();
        $this->resTenantId = $tenantId;
        $this->modalResolucion = true;
    }

    public function editarResolucion(int $id): void
    {
        $r = FeResolucion::findOrFail($id);
        $this->resolucionId       = $r->id;
        $this->resTenantId        = $r->tenant_id;
        $this->res_tipo_documento = $r->tipo_documento ?: 'factura';
        $this->res_prefijo        = (string) $r->prefijo;
        $this->res_numero_desde   = $r->numero_desde;
        $this->res_numero_hasta   = $r->numero_hasta;
        $this->res_numero_actual  = $r->numero_actual;
        $this->res_numero_resolucion = (string) $r->numero_resolucion;
        $this->res_fecha_desde    = optional($r->fecha_desde)->format('Y-m-d');
        $this->res_fecha_hasta    = optional($r->fecha_hasta)->format('Y-m-d');
        $this->res_clave_tecnica  = '';
        $this->res_activa         = (bool) $r->activa;
        $this->resetValidation();
        $this->modalResolucion = true;
    }

    public function guardarResolucion(): void
    {
        $this->validate([
            'resTenantId'      => 'required|integer|exists:tenants,id',
            'res_tipo_documento' => 'required|string|max:20',
            'res_prefijo'      => 'nullable|string|max:10',
            'res_numero_desde' => 'required|integer|min:1',
            'res_numero_hasta' => 'required|integer|gt:res_numero_desde',
            'res_numero_actual'=> 'nullable|integer|min:0',
            'res_numero_resolucion' => 'nullable|string|max:40',
            'res_fecha_desde'  => 'nullable|date',
            'res_fecha_hasta'  => 'nullable|date',
            'res_activa'       => 'boolean',
        ]);

        $data = [
            'tenant_id'        => $this->resTenantId,
            'tipo_documento'   => $this->res_tipo_documento,
            'prefijo'          => trim($this->res_prefijo) ?: null,
            'numero_desde'     => $this->res_numero_desde,
            'numero_hasta'     => $this->res_numero_hasta,
            'numero_actual'    => $this->res_numero_actual ?? ($this->res_numero_desde - 1),
            'numero_resolucion'=> trim($this->res_numero_resolucion) ?: null,
            'fecha_desde'      => $this->res_fecha_desde ?: null,
            'fecha_hasta'      => $this->res_fecha_hasta ?: null,
            'activa'           => $this->res_activa,
        ];
        if (trim($this->res_clave_tecnica) !== '') {
            $data['clave_tecnica'] = trim($this->res_clave_tecnica);
        }

        if ($this->resolucionId) {
            FeResolucion::whereKey($this->resolucionId)->update($data);
        } else {
            FeResolucion::create($data);
        }

        $this->modalResolucion = false;
        $this->resetResolucion();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Resolución guardada.']);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function generarApiKey(): string
    {
        return 'fe_' . bin2hex(random_bytes(24));
    }

    private function resetEmisor(): void
    {
        $this->configId = null; $this->tenantId = null;
        $this->nit = ''; $this->dv = ''; $this->razon_social = '';
        $this->tipo_persona = 'juridica'; $this->municipio_codigo = '';
        $this->responsabilidades_text = ''; $this->ambiente = 'habilitacion';
        $this->software_id = ''; $this->software_pin = ''; $this->test_set_id = '';
        $this->certificado = null; $this->certificado_password = '';
        $this->certificado_vence_at = null; $this->activa = true;
        $this->resetValidation();
    }

    private function resetResolucion(): void
    {
        $this->resolucionId = null; $this->resTenantId = null;
        $this->res_tipo_documento = 'factura'; $this->res_prefijo = '';
        $this->res_numero_desde = null; $this->res_numero_hasta = null;
        $this->res_numero_actual = null; $this->res_numero_resolucion = '';
        $this->res_fecha_desde = null; $this->res_fecha_hasta = null;
        $this->res_clave_tecnica = ''; $this->res_activa = true;
        $this->resetValidation();
    }

    public function render()
    {
        $emisores = FeConfiguracion::query()
            ->orderByDesc('id')
            ->get()
            ->map(function ($c) {
                $c->tenant_nombre = optional(Tenant::find($c->tenant_id))->nombre
                    ?? ('Tenant #' . $c->tenant_id);
                $c->resoluciones = FeResolucion::where('tenant_id', $c->tenant_id)
                    ->orderByDesc('activa')->orderByDesc('id')->get();
                return $c;
            });

        $tenants = Tenant::orderBy('nombre')->get(['id', 'nombre']);

        return view('livewire.admin.facturacion.index', compact('emisores', 'tenants'))
            ->layout('layouts.app');
    }
}
