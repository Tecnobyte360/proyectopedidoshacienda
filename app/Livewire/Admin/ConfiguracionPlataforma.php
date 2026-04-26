<?php

namespace App\Livewire\Admin;

use App\Models\ConfiguracionPlataforma as ConfiguracionPlataformaModel;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class ConfiguracionPlataforma extends Component
{
    public string $nombre            = '';
    public string $subtitulo         = '';
    public string $color_primario    = '#d68643';
    public string $color_secundario  = '#a85f24';
    public string $logo_url_actual   = '';
    public string $email_soporte     = '';
    public string $telefono_soporte  = '';
    public string $sitio_web         = '';

    // Credenciales del SuperAdmin TecnoByteApp (compartidas por todos los tenants)
    public string $whatsapp_admin_email     = '';
    public string $whatsapp_admin_password  = '';
    public string $whatsapp_api_base_url    = 'https://wa-api.tecnobyteapp.com:1422';

    /** Logo nuevo (data URL base64 desde Alpine FileReader) */
    public string $logo_data_url = '';
    public string $logo_nombre   = '';

    public function mount(): void
    {
        $cfg = ConfiguracionPlataformaModel::actual();

        $this->nombre           = (string) $cfg->nombre;
        $this->subtitulo        = (string) $cfg->subtitulo;
        $this->color_primario   = (string) $cfg->color_primario;
        $this->color_secundario = (string) $cfg->color_secundario;
        $this->logo_url_actual  = (string) ($cfg->logo_url ?? '');
        $this->email_soporte    = (string) ($cfg->email_soporte ?? '');
        $this->telefono_soporte = (string) ($cfg->telefono_soporte ?? '');
        $this->sitio_web        = (string) ($cfg->sitio_web ?? '');

        $this->whatsapp_admin_email    = (string) ($cfg->whatsapp_admin_email ?? '');
        $this->whatsapp_admin_password = (string) ($cfg->whatsapp_admin_password ?? '');
        $this->whatsapp_api_base_url   = (string) ($cfg->whatsapp_api_base_url ?: 'https://wa-api.tecnobyteapp.com:1422');
    }

    protected function rules(): array
    {
        return [
            'nombre'            => 'required|string|max:80',
            'subtitulo'         => 'nullable|string|max:120',
            'color_primario'    => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'color_secundario'  => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'email_soporte'     => 'nullable|email|max:120',
            'telefono_soporte'  => 'nullable|string|max:30',
            'sitio_web'         => 'nullable|url|max:200',
            'logo_data_url'     => 'nullable|string',
            'whatsapp_admin_email'     => 'nullable|email|max:120',
            'whatsapp_admin_password'  => 'nullable|string|max:200',
            'whatsapp_api_base_url'    => 'nullable|string|max:200',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $cfg  = ConfiguracionPlataformaModel::actual();

        // Procesar logo nuevo si vino como base64
        $logoUrl = $cfg->logo_url;
        if (!empty($data['logo_data_url']) && str_starts_with($data['logo_data_url'], 'data:')) {
            if (preg_match('#^data:([^;]+);base64,(.+)$#', $data['logo_data_url'], $m)) {
                $bytes = base64_decode($m[2], true);
                if ($bytes !== false && strlen($bytes) <= 2 * 1024 * 1024) {
                    $ext = match (true) {
                        str_contains($m[1], 'png')  => 'png',
                        str_contains($m[1], 'jpeg') => 'jpg',
                        str_contains($m[1], 'jpg')  => 'jpg',
                        str_contains($m[1], 'svg')  => 'svg',
                        str_contains($m[1], 'webp') => 'webp',
                        default                     => 'png',
                    };
                    $filename = 'plataforma-logo-' . now()->timestamp . '.' . $ext;
                    Storage::disk('public')->put('plataforma/' . $filename, $bytes);
                    $logoUrl = '/storage/plataforma/' . $filename;
                    $this->logo_url_actual = $logoUrl;
                }
            }
        }

        $cfg->update([
            'nombre'            => $data['nombre'],
            'subtitulo'         => $data['subtitulo'] ?: 'Plataforma SaaS',
            'color_primario'    => $data['color_primario'],
            'color_secundario'  => $data['color_secundario'],
            'logo_url'          => $logoUrl,
            'email_soporte'     => $data['email_soporte'] ?: null,
            'telefono_soporte'  => $data['telefono_soporte'] ?: null,
            'sitio_web'         => $data['sitio_web'] ?: null,
            'whatsapp_admin_email'    => $data['whatsapp_admin_email']    ?: null,
            'whatsapp_admin_password' => $data['whatsapp_admin_password'] ?: null,
            'whatsapp_api_base_url'   => $data['whatsapp_api_base_url']   ?: 'https://wa-api.tecnobyteapp.com:1422',
        ]);

        ConfiguracionPlataformaModel::limpiarCache();

        $this->logo_data_url = '';
        $this->logo_nombre   = '';

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Configuración de la plataforma guardada.',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.configuracion-plataforma')->layout('layouts.app');
    }
}
