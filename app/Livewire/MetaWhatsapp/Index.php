<?php

namespace App\Livewire\MetaWhatsapp;

use App\Models\MensajeWhatsapp;
use App\Models\MetaWhatsappConfig;
use App\Models\MetaWhatsappDisparador;
use App\Models\MetaWhatsappPlantilla;
use App\Services\Meta\MetaWhatsappCloudService;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public string $tab = 'configuracion';

    /* ── CONFIGURACIÓN ──────────────────────────────────────── */
    public ?int   $configId = null;
    public string $phone_number_id = '';
    public string $waba_id         = '';
    public string $access_token    = '';
    public string $api_version     = 'v20.0';
    public string $verify_token    = '';
    public string $app_secret      = '';
    public bool   $activo          = false;
    public string $default_lang    = 'es';
    public string $codigo_pais     = '57';
    public string $display_name    = '';

    /* ── PLANTILLA (modal) ──────────────────────────────────── */
    public bool   $modalPlantilla = false;
    public ?int   $plantilla_id = null;
    public string $tpl_nombre = '';
    public string $tpl_idioma = 'es';
    public string $tpl_categoria = 'UTILITY';
    public string $tpl_estado = 'borrador';
    public string $tpl_descripcion = '';
    public string $tpl_body = '';
    public string $tpl_footer = '';
    public bool   $tpl_activa = true;

    /* ── DISPARADOR (modal) ─────────────────────────────────── */
    public bool   $modalDisparador = false;
    public ?int   $disp_id = null;
    public string $disp_evento = '';
    public ?int   $disp_plantilla_id = null;
    public string $disp_variables_map = '';   // JSON crudo {1:"{cliente_nombre}", 2:"{total}"}
    public bool   $disp_activo = true;
    public string $disp_descripcion = '';

    /* ── ENVÍO PRUEBA ───────────────────────────────────────── */
    public string $prueba_modo = 'texto';     // 'texto' | 'plantilla'
    public string $prueba_telefono = '';
    public string $prueba_mensaje = '';
    public ?int   $prueba_plantilla_id = null;
    public array  $prueba_variables = [];     // ['1' => 'Stiven', '2' => '50000']

    public function mount(): void
    {
        $this->cargarConfig();
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

    private function cargarConfig(): void
    {
        $c = MetaWhatsappConfig::first();
        if (!$c) {
            $this->verify_token = Str::random(24);
            return;
        }
        $this->configId        = $c->id;
        $this->phone_number_id = (string) $c->phone_number_id;
        $this->waba_id         = (string) ($c->waba_id ?? '');
        $this->access_token    = (string) $c->access_token;
        $this->api_version     = (string) ($c->api_version ?? 'v20.0');
        $this->verify_token    = (string) $c->verify_token;
        $this->app_secret      = (string) ($c->app_secret ?? '');
        $this->activo          = (bool) $c->activo;
        $this->default_lang    = (string) ($c->default_lang ?? 'es');
        $this->display_name    = (string) ($c->display_name ?? '');
    }

    /* ── CONFIG ─────────────────────────────────────────────── */

    public function guardarConfig(): void
    {
        $data = $this->validate([
            'phone_number_id' => 'required|string|max:64',
            'waba_id'         => 'nullable|string|max:64',
            'access_token'    => 'required|string',
            'api_version'     => 'required|string|max:16',
            'verify_token'    => 'required|string|max:128',
            'app_secret'      => 'nullable|string',
            'activo'          => 'boolean',
            'default_lang'    => 'required|string|max:8',
            'display_name'    => 'nullable|string|max:120',
        ]);

        MetaWhatsappConfig::updateOrCreate(
            ['id' => $this->configId],
            $data
        );
        $this->cargarConfig();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Configuración guardada.']);
    }

    public function probarConexion(): void
    {
        if (!$this->configId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Guarda la configuración primero.']);
            return;
        }
        if (trim($this->prueba_telefono) === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Indica un teléfono en la tab "Envío prueba".']);
            $this->tab = 'envio_prueba';
            return;
        }
        $ok = app(MetaWhatsappCloudService::class)
            ->enviarTexto($this->prueba_telefono, '✅ Prueba Meta ' . now()->format('H:i'), $this->configId ? MetaWhatsappConfig::find($this->configId)?->tenant_id : null);
        $this->dispatch('notify', [
            'type'    => $ok ? 'success' : 'error',
            'message' => $ok ? 'Mensaje enviado por Meta.' : 'Falló (revisa logs y credenciales).',
        ]);
    }

    /* ── PLANTILLAS ─────────────────────────────────────────── */

    public function sincronizarPlantillas(): void
    {
        $r = app(MetaWhatsappCloudService::class)->sincronizarPlantillas();
        $this->dispatch('notify', [
            'type'    => $r['ok'] ? 'success' : 'error',
            'message' => $r['ok']
                ? "Sincronizadas {$r['importadas']} de {$r['total']} plantillas."
                : ('Falló: ' . implode(' | ', $r['errores'])),
        ]);
    }

    public function abrirPlantillaCrear(): void
    {
        $this->reset(['plantilla_id', 'tpl_nombre', 'tpl_descripcion', 'tpl_body', 'tpl_footer']);
        $this->tpl_idioma = 'es';
        $this->tpl_categoria = 'UTILITY';
        $this->tpl_estado = 'borrador';
        $this->tpl_activa = true;
        $this->resetValidation();
        $this->modalPlantilla = true;
    }

    public function abrirPlantillaEditar(int $id): void
    {
        $p = MetaWhatsappPlantilla::findOrFail($id);
        $this->plantilla_id    = $p->id;
        $this->tpl_nombre      = $p->nombre;
        $this->tpl_idioma      = $p->idioma;
        $this->tpl_categoria   = $p->categoria;
        $this->tpl_estado      = $p->estado;
        $this->tpl_descripcion = (string) $p->descripcion;
        $this->tpl_body        = (string) $p->body_preview;
        $this->tpl_footer      = (string) $p->footer;
        $this->tpl_activa      = (bool) $p->activa;
        $this->modalPlantilla = true;
    }

    public function guardarPlantilla(): void
    {
        $data = $this->validate([
            'tpl_nombre'      => ['required', 'string', 'max:128', 'regex:/^[a-z0-9_]+$/'],
            'tpl_idioma'      => 'required|string|max:12',
            'tpl_categoria'   => 'required|string|max:32',
            'tpl_estado'      => 'required|string|max:32',
            'tpl_descripcion' => 'nullable|string|max:255',
            'tpl_body'        => 'nullable|string',
            'tpl_footer'      => 'nullable|string',
            'tpl_activa'      => 'boolean',
        ], [
            'tpl_nombre.regex' => 'El nombre debe ser snake_case (minúsculas, números, guion bajo).',
        ]);

        MetaWhatsappPlantilla::updateOrCreate(
            ['id' => $this->plantilla_id],
            [
                'nombre'        => $data['tpl_nombre'],
                'idioma'        => $data['tpl_idioma'],
                'categoria'     => $data['tpl_categoria'],
                'estado'        => $data['tpl_estado'],
                'descripcion'   => $data['tpl_descripcion'] ?: null,
                'body_preview'  => $data['tpl_body'] ?: null,
                'footer'        => $data['tpl_footer'] ?: null,
                'num_variables' => MetaWhatsappPlantilla::contarVariables($data['tpl_body'] ?? ''),
                'activa'        => $data['tpl_activa'],
            ]
        );

        $this->modalPlantilla = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Plantilla guardada.']);
    }

    public function eliminarPlantilla(int $id): void
    {
        MetaWhatsappPlantilla::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Plantilla eliminada.']);
    }

    public function toggleActivaPlantilla(int $id): void
    {
        $p = MetaWhatsappPlantilla::findOrFail($id);
        $p->update(['activa' => !$p->activa]);
    }

    /* ── DISPARADORES ───────────────────────────────────────── */

    public function abrirDisparadorCrear(): void
    {
        $this->reset(['disp_id', 'disp_evento', 'disp_plantilla_id', 'disp_variables_map', 'disp_descripcion']);
        $this->disp_activo = true;
        $this->resetValidation();
        $this->modalDisparador = true;
    }

    public function abrirDisparadorEditar(int $id): void
    {
        $d = MetaWhatsappDisparador::findOrFail($id);
        $this->disp_id            = $d->id;
        $this->disp_evento        = $d->evento;
        $this->disp_plantilla_id  = $d->plantilla_id;
        $this->disp_variables_map = json_encode($d->variables_map ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->disp_activo        = (bool) $d->activo;
        $this->disp_descripcion   = (string) $d->descripcion;
        $this->modalDisparador = true;
    }

    public function guardarDisparador(): void
    {
        $data = $this->validate([
            'disp_evento'        => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'disp_plantilla_id'  => 'required|exists:meta_whatsapp_plantillas,id',
            'disp_variables_map' => 'nullable|string',
            'disp_activo'        => 'boolean',
            'disp_descripcion'   => 'nullable|string|max:255',
        ], [
            'disp_evento.regex' => 'El evento debe ser snake_case.',
        ]);

        $map = null;
        if (!empty($data['disp_variables_map'])) {
            $decoded = json_decode($data['disp_variables_map'], true);
            if (!is_array($decoded)) {
                $this->addError('disp_variables_map', 'El JSON no es válido.');
                return;
            }
            $map = $decoded;
        }

        MetaWhatsappDisparador::updateOrCreate(
            ['id' => $this->disp_id],
            [
                'evento'        => $data['disp_evento'],
                'plantilla_id'  => $data['disp_plantilla_id'],
                'variables_map' => $map,
                'activo'        => $data['disp_activo'],
                'descripcion'   => $data['disp_descripcion'] ?: null,
            ]
        );

        $this->modalDisparador = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Disparador guardado.']);
    }

    public function eliminarDisparador(int $id): void
    {
        MetaWhatsappDisparador::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Disparador eliminado.']);
    }

    public function toggleActivoDisparador(int $id): void
    {
        $d = MetaWhatsappDisparador::findOrFail($id);
        $d->update(['activo' => !$d->activo]);
    }

    /* ── ENVÍO PRUEBA ───────────────────────────────────────── */

    public function enviarPrueba(): void
    {
        $tel = trim($this->prueba_telefono);
        if ($tel === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Teléfono requerido.']);
            return;
        }
        if (!$this->configId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Configura Meta primero.']);
            return;
        }
        $tenantId = MetaWhatsappConfig::find($this->configId)?->tenant_id;

        $ok = false;
        if ($this->prueba_modo === 'plantilla' && $this->prueba_plantilla_id) {
            $p = MetaWhatsappPlantilla::find($this->prueba_plantilla_id);
            if (!$p) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Plantilla no encontrada.']);
                return;
            }
            $vars = array_values(array_filter($this->prueba_variables, fn ($v) => $v !== ''));
            $ok = app(MetaWhatsappCloudService::class)->enviarPlantilla(
                $tel, $p->nombre, $vars, $tenantId, $p->idioma
            );
        } else {
            $msg = trim($this->prueba_mensaje) ?: ('Prueba Meta ' . now()->format('H:i'));
            $ok = app(MetaWhatsappCloudService::class)->enviarTexto($tel, $msg, $tenantId);
        }

        $this->dispatch('notify', [
            'type'    => $ok ? 'success' : 'error',
            'message' => $ok ? '✅ Enviado.' : '❌ Falló (revisa el log o las credenciales).',
        ]);
    }

    public function updatedPruebaPlantillaId(): void
    {
        $p = $this->prueba_plantilla_id ? MetaWhatsappPlantilla::find($this->prueba_plantilla_id) : null;
        $n = $p?->num_variables ?? 0;
        $this->prueba_variables = array_fill(1, max($n, 0), '');
    }

    /* ── RENDER ─────────────────────────────────────────────── */

    public function render()
    {
        $plantillas = MetaWhatsappPlantilla::orderByDesc('activa')->orderBy('nombre')->get();
        $disparadores = MetaWhatsappDisparador::with('plantilla')->orderBy('evento')->get();

        $historial = collect();
        if ($this->tab === 'historial') {
            $historial = MensajeWhatsapp::query()
                ->where(function ($q) {
                    $q->whereJsonContains('meta->provider', 'meta')
                      ->orWhereJsonContains('meta->origen', 'meta');
                })
                ->latest('id')
                ->limit(100)
                ->get();
        }

        $webhookUrl = rtrim(config('app.url', ''), '/') . '/api/meta/whatsapp/webhook';

        return view('livewire.meta-whatsapp.index', [
            'plantillas'      => $plantillas,
            'disparadores'    => $disparadores,
            'historial'       => $historial,
            'webhookUrl'      => $webhookUrl,
            'eventosSugeridos'=> MetaWhatsappDisparador::eventosSugeridos(),
        ])->layout('layouts.app');
    }
}
