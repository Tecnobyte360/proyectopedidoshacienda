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

    /**
     * Lista las conexiones disponibles en TecnoByteApp y a qué tenant
     * están asignadas (si alguna). Útil para que el superadmin gestione
     * todo desde un solo lugar.
     */
    public function getConexionesProperty(): array
    {
        try {
            $resolver = app(\App\Services\WhatsappResolverService::class);
            // El superadmin usa siempre las credenciales de la plataforma,
            // sin pasar por ningún tenant. Forzamos eso temporal con null.
            $cred = [
                'email'        => $this->whatsapp_admin_email,
                'password'     => $this->whatsapp_admin_password,
                'api_base_url' => $this->whatsapp_api_base_url ?: 'https://wa-api.tecnobyteapp.com:1422',
            ];

            if (empty($cred['email']) || empty($cred['password'])) return [];

            $base = rtrim($cred['api_base_url'], '/');
            $login = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)
                ->post("{$base}/auth/login", [
                    'email' => $cred['email'], 'password' => $cred['password']
                ]);

            if (!$login->successful() || !$login->json('token')) return [];
            $token = $login->json('token');

            $lst = \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->timeout(10)
                ->get("{$base}/whatsapp/");
            if (!$lst->successful()) return [];

            // Mapa connection_id → tenant
            $tenants = \App\Services\TenantManager::class
                ? app(\App\Services\TenantManager::class)->withoutTenant(
                    fn() => \App\Models\Tenant::where('activo', true)->get(['id','nombre','slug','whatsapp_config'])
                  )
                : collect();

            $mapaIds = [];
            foreach ($tenants as $t) {
                foreach (($t->whatsapp_config['connection_ids'] ?? []) as $cid) {
                    $mapaIds[(int) $cid] = ['id' => $t->id, 'nombre' => $t->nombre, 'slug' => $t->slug];
                }
            }

            $conns = collect($lst->json('whatsapps', []))->map(function ($w) use ($mapaIds) {
                $id = (int) $w['id'];
                return [
                    'id'           => $id,
                    'name'         => $w['name'] ?? '',
                    'phoneNumber'  => $w['phoneNumber'] ?? '',
                    'profileName'  => $w['profileName'] ?? '',
                    'status'       => strtoupper($w['status'] ?? '???'),
                    'isDefault'    => (bool) ($w['isDefault'] ?? false),
                    'asignadoA'    => $mapaIds[$id] ?? null,
                ];
            })->values()->all();

            return $conns;
        } catch (\Throwable $e) {
            \Log::warning('No se pudo listar conexiones plataforma: ' . $e->getMessage());
            return [];
        }
    }

    public function getTenantsProperty()
    {
        return app(\App\Services\TenantManager::class)->withoutTenant(
            fn() => \App\Models\Tenant::where('activo', true)->orderBy('nombre')->get(['id','nombre','slug','whatsapp_config'])
        );
    }

    /**
     * Asigna (o desasigna) una conexión a un tenant.
     * - Si $tenantId es 0 / vacío → quita la conexión de cualquier tenant.
     * - Si $tenantId tiene valor → quita de cualquier otro tenant primero,
     *   y la añade a connection_ids del tenant indicado.
     */
    public function asignarConexion(int $connectionId, ?int $tenantId): void
    {
        app(\App\Services\TenantManager::class)->withoutTenant(function () use ($connectionId, $tenantId) {
            $tenants = \App\Models\Tenant::all();

            foreach ($tenants as $t) {
                $cfg = $t->whatsapp_config ?? [];
                $ids = collect($cfg['connection_ids'] ?? [])->map(fn ($x) => (int) $x);

                if ($tenantId && (int) $t->id === (int) $tenantId) {
                    // Añadir si no estaba
                    if (!$ids->contains($connectionId)) {
                        $ids->push($connectionId);
                        $cfg['connection_ids'] = $ids->unique()->values()->all();
                        $t->whatsapp_config = $cfg;
                        $t->save();
                    }
                } else {
                    // Quitar de cualquier otro tenant que la tenga
                    if ($ids->contains($connectionId)) {
                        $cfg['connection_ids'] = $ids->reject(fn ($x) => $x === $connectionId)->values()->all();
                        $t->whatsapp_config = $cfg;
                        $t->save();
                    }
                }
            }
        });

        \Cache::forget('wa_connection_to_tenant_map');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $tenantId
                ? "✓ Conexión #{$connectionId} asignada al tenant."
                : "✓ Conexión #{$connectionId} desasignada.",
        ]);
    }

    public function render()
    {
        return view('livewire.admin.configuracion-plataforma', [
            'conexiones' => $this->conexiones,
            'tenants'    => $this->tenants,
        ])->layout('layouts.app');
    }
}
