<?php

namespace App\Livewire\MetaWhatsapp;

use App\Models\MetaWhatsappConfig;
use App\Services\Meta\MetaWhatsappCloudService;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public bool   $modalAbierto = false;
    public ?int   $editandoId = null;

    public string $phone_number_id = '';
    public string $waba_id         = '';
    public string $access_token    = '';
    public string $api_version     = 'v20.0';
    public string $verify_token    = '';
    public string $app_secret      = '';
    public bool   $activo          = false;
    public string $default_lang    = 'es';
    public string $display_name    = '';

    public string $telefonoPrueba = '';

    protected function rules(): array
    {
        return [
            'phone_number_id' => 'required|string|max:64',
            'waba_id'         => 'nullable|string|max:64',
            'access_token'    => 'required|string',
            'api_version'     => 'required|string|max:16',
            'verify_token'    => 'required|string|max:128',
            'app_secret'      => 'nullable|string',
            'activo'          => 'boolean',
            'default_lang'    => 'required|string|max:8',
            'display_name'    => 'nullable|string|max:120',
        ];
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['editandoId', 'phone_number_id', 'waba_id', 'access_token',
                      'verify_token', 'app_secret', 'activo', 'display_name']);
        $this->api_version  = 'v20.0';
        $this->default_lang = 'es';
        // Sugerir un verify_token random para que el admin no tenga que inventar
        $this->verify_token = Str::random(24);
        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $c = MetaWhatsappConfig::findOrFail($id);
        $this->editandoId       = $c->id;
        $this->phone_number_id  = (string) $c->phone_number_id;
        $this->waba_id          = (string) ($c->waba_id ?? '');
        $this->access_token     = (string) $c->access_token;
        $this->api_version      = (string) ($c->api_version ?? 'v20.0');
        $this->verify_token     = (string) $c->verify_token;
        $this->app_secret       = (string) ($c->app_secret ?? '');
        $this->activo           = (bool) $c->activo;
        $this->default_lang     = (string) ($c->default_lang ?? 'es');
        $this->display_name     = (string) ($c->display_name ?? '');
        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    public function guardar(): void
    {
        $data = $this->validate();

        // Si se activa, desactivar otras configs del mismo tenant (solo una activa).
        if ($this->activo) {
            MetaWhatsappConfig::where('activo', true)
                ->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))
                ->update(['activo' => false]);
        }

        MetaWhatsappConfig::updateOrCreate(
            ['id' => $this->editandoId],
            $data
        );

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Configuración Meta actualizada.' : 'Configuración Meta creada.',
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $c = MetaWhatsappConfig::findOrFail($id);
        // Si vamos a activar, desactivar las otras
        if (!$c->activo) {
            MetaWhatsappConfig::where('activo', true)
                ->where('id', '!=', $c->id)
                ->update(['activo' => false]);
        }
        $c->activo = !$c->activo;
        $c->save();
    }

    public function eliminar(int $id): void
    {
        MetaWhatsappConfig::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Configuración eliminada.']);
    }

    public function probarEnvio(int $id): void
    {
        $c = MetaWhatsappConfig::findOrFail($id);
        $tel = trim($this->telefonoPrueba);
        if ($tel === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Indica el teléfono de prueba.']);
            return;
        }

        $ok = app(MetaWhatsappCloudService::class)->enviarTexto(
            $tel,
            "✅ Prueba Meta WhatsApp desde el panel — " . now()->format('H:i'),
            $c->tenant_id
        );

        $this->dispatch('notify', [
            'type'    => $ok ? 'success' : 'error',
            'message' => $ok ? 'Mensaje enviado por Meta.' : 'Falló el envío (revisa logs).',
        ]);
    }

    public function render()
    {
        $configs = MetaWhatsappConfig::orderByDesc('activo')->orderBy('id')->get();
        $webhookUrl = rtrim(config('app.url', ''), '/') . '/api/meta/whatsapp/webhook';

        return view('livewire.meta-whatsapp.index', [
            'configs'    => $configs,
            'webhookUrl' => $webhookUrl,
        ])->layout('layouts.app');
    }
}
