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

    // 💳 Wompi del dueño Kivox (para cobrar mensualidades a tenants)
    public string $saas_wompi_modo             = 'sandbox';
    public string $saas_wompi_public_key       = '';
    public string $saas_wompi_private_key      = '';
    public string $saas_wompi_integrity_secret = '';
    public string $saas_wompi_events_secret    = '';
    public string $saas_wompi_redirect_url     = '';

    // ⚙️ Política de cobros SaaS
    public bool $saas_billing_activo      = true;
    public int  $saas_dias_antes_factura  = 7;
    public int  $saas_dias_gracia         = 7;
    public array $saas_horas_envio        = ['10:00'];
    public string $nuevaHora              = ''; // input temporal para agregar (vacío para que usuario elija)
    public bool $saas_aviso_preaviso      = true;
    public bool $saas_aviso_vence_hoy     = true;
    public bool $saas_aviso_vencio_ayer   = true;
    public bool $saas_aviso_urgencia      = true;

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

        $this->saas_wompi_modo             = (string) ($cfg->saas_wompi_modo ?: 'sandbox');
        $this->saas_wompi_public_key       = (string) ($cfg->saas_wompi_public_key ?? '');
        $this->saas_wompi_private_key      = (string) ($cfg->saas_wompi_private_key ?? '');
        $this->saas_wompi_integrity_secret = (string) ($cfg->saas_wompi_integrity_secret ?? '');
        $this->saas_wompi_events_secret    = (string) ($cfg->saas_wompi_events_secret ?? '');
        $this->saas_wompi_redirect_url     = (string) ($cfg->saas_wompi_redirect_url ?? '');

        $this->saas_billing_activo     = (bool)   ($cfg->saas_billing_activo ?? true);
        $this->saas_dias_antes_factura = (int)    ($cfg->saas_dias_antes_factura ?? 7);
        $this->saas_dias_gracia        = (int)    ($cfg->saas_dias_gracia ?? 7);
        $this->saas_horas_envio        = is_array($cfg->saas_horas_envio ?? null) && count($cfg->saas_horas_envio)
                                          ? array_values($cfg->saas_horas_envio)
                                          : ['10:00'];
        $this->saas_aviso_preaviso     = (bool)   ($cfg->saas_aviso_preaviso ?? true);
        $this->saas_aviso_vence_hoy    = (bool)   ($cfg->saas_aviso_vence_hoy ?? true);
        $this->saas_aviso_vencio_ayer  = (bool)   ($cfg->saas_aviso_vencio_ayer ?? true);
        $this->saas_aviso_urgencia     = (bool)   ($cfg->saas_aviso_urgencia ?? true);
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
            'saas_wompi_modo'             => 'required|in:sandbox,produccion',
            'saas_wompi_public_key'       => 'nullable|string|max:200',
            'saas_wompi_private_key'      => 'nullable|string|max:200',
            'saas_wompi_integrity_secret' => 'nullable|string|max:200',
            'saas_wompi_events_secret'    => 'nullable|string|max:200',
            'saas_wompi_redirect_url'     => 'nullable|url|max:255',
            'saas_billing_activo'         => 'boolean',
            'saas_dias_antes_factura'     => 'required|integer|min:1|max:60',
            'saas_dias_gracia'            => 'required|integer|min:0|max:60',
            'saas_horas_envio'            => 'required|array|min:1|max:8',
            'saas_horas_envio.*'          => ['required','regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'saas_aviso_preaviso'         => 'boolean',
            'saas_aviso_vence_hoy'        => 'boolean',
            'saas_aviso_vencio_ayer'      => 'boolean',
            'saas_aviso_urgencia'         => 'boolean',
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
            'saas_wompi_modo'             => $data['saas_wompi_modo'],
            'saas_wompi_public_key'       => $data['saas_wompi_public_key']       ?: null,
            'saas_wompi_private_key'      => $data['saas_wompi_private_key']      ?: null,
            'saas_wompi_integrity_secret' => $data['saas_wompi_integrity_secret'] ?: null,
            'saas_wompi_events_secret'    => $data['saas_wompi_events_secret']    ?: null,
            'saas_wompi_redirect_url'     => $data['saas_wompi_redirect_url']     ?: null,
            'saas_billing_activo'         => $data['saas_billing_activo'],
            'saas_dias_antes_factura'     => $data['saas_dias_antes_factura'],
            'saas_dias_gracia'            => $data['saas_dias_gracia'],
            'saas_horas_envio'            => array_values(array_unique($data['saas_horas_envio'])),
            'saas_aviso_preaviso'         => $data['saas_aviso_preaviso'],
            'saas_aviso_vence_hoy'        => $data['saas_aviso_vence_hoy'],
            'saas_aviso_vencio_ayer'      => $data['saas_aviso_vencio_ayer'],
            'saas_aviso_urgencia'         => $data['saas_aviso_urgencia'],
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
     * Lista TODAS las conexiones de TODOS los tenants + del superadmin,
     * unificando en una tabla. Cada tenant tiene su propia cuenta en
     * TecnoByteApp y solo ve SUS conexiones, así que iteramos por cada
     * conjunto de credenciales (superadmin + tenants con cuenta propia)
     * y unificamos.
     */
    public function getConexionesProperty(): array
    {
        $apiBase = rtrim($this->whatsapp_api_base_url ?: 'https://wa-api.tecnobyteapp.com:1422', '/');

        // Recolectar TODOS los pares email/password disponibles para listar
        $credenciales = [];

        // 1) Credenciales del superadmin (si están configuradas)
        if (!empty($this->whatsapp_admin_email) && !empty($this->whatsapp_admin_password)) {
            $credenciales['superadmin'] = [
                'email'    => $this->whatsapp_admin_email,
                'password' => $this->whatsapp_admin_password,
                'tag'      => 'Superadmin plataforma',
                'tenant_id' => null,
            ];
        }

        // 2) Credenciales propias de cada tenant
        $tenants = app(\App\Services\TenantManager::class)->withoutTenant(
            fn () => \App\Models\Tenant::where('activo', true)
                        ->orderBy('nombre')
                        ->get(['id','nombre','slug','whatsapp_config'])
        );

        $mapaIdsTenant = [];   // connection_id → tenant
        foreach ($tenants as $t) {
            $cfg = $t->whatsapp_config ?? [];
            foreach (($cfg['connection_ids'] ?? []) as $cid) {
                $mapaIdsTenant[(int) $cid] = ['id' => $t->id, 'nombre' => $t->nombre, 'slug' => $t->slug];
            }

            if (!empty($cfg['email']) && !empty($cfg['password'])) {
                $key = "tenant_{$t->id}";
                $credenciales[$key] = [
                    'email'     => $cfg['email'],
                    'password'  => $cfg['password'],
                    'tag'       => $t->nombre,
                    'tenant_id' => $t->id,
                ];
            }
        }

        if (empty($credenciales)) return [];

        // Hacer login + listar con cada par de credenciales y unificar
        $todas = [];   // id → conexion
        foreach ($credenciales as $key => $cred) {
            try {
                $login = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(8)
                    ->post("{$apiBase}/auth/login", [
                        'email' => $cred['email'], 'password' => $cred['password']
                    ]);

                if (!$login->successful() || !$login->json('token')) continue;

                $token = $login->json('token');
                $lst = \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->timeout(8)
                    ->get("{$apiBase}/whatsapp/");

                if (!$lst->successful()) continue;

                foreach ($lst->json('whatsapps', []) as $w) {
                    $id = (int) ($w['id'] ?? 0);
                    if (!$id) continue;

                    // Si ya estaba registrada por otro usuario, solo añadimos su tag
                    if (isset($todas[$id])) {
                        $todas[$id]['vistoPor'][] = $cred['tag'];
                        continue;
                    }

                    $todas[$id] = [
                        'id'           => $id,
                        'name'         => $w['name'] ?? '',
                        'phoneNumber'  => $w['phoneNumber'] ?? '',
                        'profileName'  => $w['profileName'] ?? '',
                        'status'       => strtoupper($w['status'] ?? '???'),
                        'isDefault'    => (bool) ($w['isDefault'] ?? false),
                        'ownerId'      => $w['ownerId'] ?? null,
                        'vistoPor'     => [$cred['tag']],
                        'asignadoA'    => $mapaIdsTenant[$id] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                \Log::warning("No se pudo listar conexiones de {$key}: " . $e->getMessage());
            }
        }

        // Ordenar: primero las asignadas, luego por id
        $legacy = collect($todas)
            ->sortBy(fn ($c) => $c['asignadoA'] ? "0_{$c['id']}" : "1_{$c['id']}")
            ->values()
            ->all();

        // 📱 Sumar conexiones Meta de cada tenant con config activa
        $metaConfigs = \App\Models\MetaWhatsappConfig::query()
            ->withoutGlobalScopes()
            ->where('activo', true)
            ->get();

        foreach ($metaConfigs as $mc) {
            $tenant = $tenants->firstWhere('id', $mc->tenant_id);

            // Validar token en vivo con un GET ligero
            $estadoToken = '???';
            $errorToken  = null;
            try {
                $base = ($mc->api_version ?: 'v25.0');
                $resp = \Illuminate\Support\Facades\Http::withToken($mc->access_token)
                    ->timeout(5)
                    ->get("https://graph.facebook.com/{$base}/me");
                if ($resp->successful()) {
                    $estadoToken = 'CONNECTED';
                } else {
                    $estadoToken = 'ERROR';
                    $errorToken  = $resp->json('error.message') ?? "HTTP {$resp->status()}";
                }
            } catch (\Throwable $e) {
                $estadoToken = 'ERROR';
                $errorToken  = 'Timeout o error red: ' . mb_substr($e->getMessage(), 0, 80);
            }

            $legacy[] = [
                'id'           => 'meta:' . $mc->phone_number_id,
                'name'         => 'Meta Cloud · ' . ($mc->display_name ?: 'WABA'),
                'phoneNumber'  => $mc->phone_number_id,
                'profileName'  => 'WABA ' . ($mc->waba_id ?: '—'),
                'status'       => $estadoToken,
                'isDefault'    => true,
                'ownerId'      => 'meta',
                'vistoPor'     => ['Meta Cloud API'],
                'asignadoA'    => $tenant ? ['id' => $tenant->id, 'nombre' => $tenant->nombre, 'slug' => $tenant->slug] : null,
                'esMeta'       => true,
                'errorMeta'    => $errorToken,
            ];
        }

        return $legacy;
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

    /** ⏰ Agrega una hora al array (validada en formato HH:MM) */
    public function agregarHora(): void
    {
        $h = trim($this->nuevaHora);
        if ($h === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona primero una hora en el reloj']);
            return;
        }
        // Normalizar HH:MM (algunos navegadores envían "HH:MM:SS")
        if (strlen($h) === 8 && substr_count($h, ':') === 2) {
            $h = substr($h, 0, 5);
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => "Formato inválido '{$h}'. Usa HH:MM (ej. 09:30)"]);
            return;
        }
        if (count($this->saas_horas_envio) >= 8) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Máximo 8 horarios por día (ya tienes 8)']);
            return;
        }
        if (in_array($h, $this->saas_horas_envio, true)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => "La hora {$h} ya está en la lista. Elige una distinta."]);
            return;
        }
        $this->saas_horas_envio[] = $h;
        sort($this->saas_horas_envio);
        $this->nuevaHora = ''; // reset para que el siguiente pick sea diferente
        $this->dispatch('notify', ['type' => 'success', 'message' => "✓ Hora {$h} agregada. Total: " . count($this->saas_horas_envio) . " horarios."]);
    }

    /** ⏰ Elimina una hora del array por índice */
    public function quitarHora(int $idx): void
    {
        if (isset($this->saas_horas_envio[$idx]) && count($this->saas_horas_envio) > 1) {
            unset($this->saas_horas_envio[$idx]);
            $this->saas_horas_envio = array_values($this->saas_horas_envio);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Debe haber al menos 1 horario configurado']);
        }
    }

    /** 💳 Prueba que las credenciales Wompi sean válidas llamando /v1/merchants/{public_key} */
    public function probarWompiSaas(): void
    {
        if (empty($this->saas_wompi_public_key)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Pega primero el public_key']);
            return;
        }
        $base = $this->saas_wompi_modo === 'produccion'
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(10)
                ->get("{$base}/merchants/{$this->saas_wompi_public_key}");
            if ($resp->successful()) {
                $name = $resp->json('data.name') ?? '—';
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "✅ Wompi OK · Comercio: {$name} · Modo: {$this->saas_wompi_modo}",
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '❌ Wompi rechazó: ' . ($resp->json('error.reason') ?? $resp->status()),
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.admin.configuracion-plataforma', [
            'conexiones' => $this->conexiones,
            'tenants'    => $this->tenants,
        ])->layout('layouts.app');
    }
}
