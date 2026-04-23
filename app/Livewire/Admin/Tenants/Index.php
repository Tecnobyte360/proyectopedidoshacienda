<?php

namespace App\Livewire\Admin\Tenants;

use App\Models\Tenant;
use App\Models\User;
use App\Services\HostingerDnsService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    public string $busqueda = '';

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    // Subdominio: log de salida del comando
    public bool   $subdomModalAbierto = false;
    public ?int   $subdomTenantId     = null;
    public string $subdomTenantNombre = '';
    public string $subdomTenantSlug   = '';
    public string $subdomDominio      = '';
    public string $subdomLog          = '';
    public bool   $subdomCorriendo    = false;
    public bool   $subdomExito        = false;
    public string $subdomEstado       = 'pendiente'; // pendiente|aplicado|error
    public ?int   $subdomIniciadoTs   = null;

    public string $nombre              = '';
    public string $slug                = '';
    public string $plan                = Tenant::PLAN_BASICO;
    public bool   $activo              = true;
    public string $contacto_nombre     = '';
    public string $contacto_email      = '';
    public string $contacto_telefono   = '';
    public string $color_primario      = '#d68643';
    public string $color_secundario    = '#a85f24';
    public ?string $logo_url_actual    = null;
    public $logo_archivo               = null;  // Livewire file upload
    public string $openai_api_key      = '';    // Key propia del tenant (opcional — si vacía usa global)
    public ?string $trial_ends_at        = null;
    public ?string $subscription_ends_at = null;
    public string $notas_internas      = '';

    // WhatsApp por tenant
    public string $whatsapp_email           = '';
    public string $whatsapp_password        = '';
    public string $whatsapp_api_base_url    = 'https://wa-api.tecnobyteapp.com:1422';
    public string $whatsapp_connection_ids  = '';   // CSV: "15, 28, 42"

    // Crear primer admin del tenant en el mismo flow
    public bool   $crear_admin_inicial   = true;
    public string $admin_nombre          = '';
    public string $admin_email           = '';
    public string $admin_password        = '';

    // 🗑️ Eliminación definitiva
    public bool   $eliminarModalAbierto  = false;
    public ?int   $eliminarTenantId      = null;
    public string $eliminarTenantNombre  = '';
    public string $eliminarTenantSlug    = '';
    public string $eliminarConfirmacion  = '';
    public bool   $eliminarCorriendo     = false;
    public string $eliminarLog           = '';

    protected function rules(): array
    {
        return [
            'nombre'              => 'required|string|max:150',
            // Regex acepta vacío (autogenerado por modelo) O kebab-case válido (a-z, 0-9, guion medio).
            // El `?` final del grupo lo hace opcional → matchea string vacío.
            // ⚠️ NO usar closure aquí: Livewire 3 no puede serializar closures en rules().
            'slug'                => [
                'nullable',
                'string',
                'max:80',
                'regex:/^([a-z0-9]+(-[a-z0-9]+)*)?$/',
                'unique:tenants,slug,' . ($this->editandoId ?? 'NULL'),
            ],
            'plan'                => 'required|in:basico,pro,empresa',
            'activo'              => 'boolean',
            'contacto_nombre'     => 'nullable|string|max:120',
            'contacto_email'      => 'nullable|email|max:150',
            'contacto_telefono'   => 'nullable|string|max:30',
            'color_primario'      => 'nullable|string|max:10',
            'color_secundario'    => 'nullable|string|max:10',
            'logo_archivo'        => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'openai_api_key'      => 'nullable|string|max:255',
            'trial_ends_at'       => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
            'notas_internas'      => 'nullable|string|max:2000',

            'whatsapp_email'         => 'nullable|email|max:150',
            'whatsapp_password'      => 'nullable|string|max:150',
            'whatsapp_api_base_url'  => 'nullable|string|max:200',
            'whatsapp_connection_ids' => 'nullable|string|max:500',

            'crear_admin_inicial' => 'boolean',
            'admin_nombre'        => 'required_if:crear_admin_inicial,true|nullable|string|max:120',
            'admin_email'         => 'required_if:crear_admin_inicial,true|nullable|email|max:150|unique:users,email',
            'admin_password'      => 'required_if:crear_admin_inicial,true|nullable|string|min:6',
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe ser kebab-case (solo a-z, 0-9 y guiones medios). Ejemplo: mi-empresa',
            'slug.unique' => 'Ese slug ya está en uso por otro tenant.',
            'admin_email.unique' => 'Ya existe un usuario con ese email.',
        ];
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    /** Alias por si Livewire tiene problemas con el nombre original */
    public function nuevoTenant(): void
    {
        $this->abrirModalCrear();
    }

    /** Alias corto para edit */
    public function editarTenant(int $id): void
    {
        $this->abrirModalEditar($id);
    }

    public function abrirModalEditar(int $id): void
    {
        $t = Tenant::findOrFail($id);

        $this->editandoId           = $t->id;
        $this->nombre               = $t->nombre;
        $this->slug                 = $t->slug;
        $this->plan                 = $t->plan;
        $this->activo               = (bool) $t->activo;
        $this->contacto_nombre      = (string) $t->contacto_nombre;
        $this->contacto_email       = (string) $t->contacto_email;
        $this->contacto_telefono    = (string) $t->contacto_telefono;
        $this->color_primario       = (string) ($t->color_primario ?: '#d68643');
        $this->color_secundario     = (string) ($t->color_secundario ?: '#a85f24');
        $this->logo_url_actual      = $t->logo_url;
        $this->logo_archivo         = null;
        $this->openai_api_key       = (string) ($t->openai_api_key ?? '');
        $this->trial_ends_at        = $t->trial_ends_at?->format('Y-m-d');
        $this->subscription_ends_at = $t->subscription_ends_at?->format('Y-m-d');
        $this->notas_internas       = (string) $t->notas_internas;

        // WhatsApp config
        $waConfig = $t->whatsapp_config ?? [];
        $this->whatsapp_email          = (string) ($waConfig['email'] ?? '');
        $this->whatsapp_password       = (string) ($waConfig['password'] ?? '');
        $this->whatsapp_api_base_url   = (string) ($waConfig['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422');
        $this->whatsapp_connection_ids = implode(', ', $waConfig['connection_ids'] ?? []);

        $this->crear_admin_inicial = false;
        $this->admin_nombre = '';
        $this->admin_email = '';
        $this->admin_password = '';

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    /**
     * Sanea el slug en vivo mientras el usuario escribe (solo a-z 0-9 -).
     */
    public function updatedSlug(): void
    {
        $this->slug = Tenant::normalizarSlug($this->slug);
    }

    public function guardar(): void
    {
        // Normalizar slug antes de validar:
        //   - Si viene vacío, dejarlo como null (el modelo lo autogenera desde el nombre)
        //   - Si viene con texto, sanearlo a kebab-case (regex queda satisfecha)
        if (trim($this->slug) === '') {
            $this->slug = '';   // Livewire necesita string, no null en propiedades typed
        } else {
            $this->slug = Tenant::normalizarSlug($this->slug);
        }

        $data = $this->validate();

        // Si el slug quedó vacío tras la validación, lo quitamos del array
        // para que NO sobrescriba el slug autogenerado por el modelo en saving().
        if (empty($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        $crear = $data['crear_admin_inicial'] ?? false;
        $adminNombre = $data['admin_nombre'] ?? '';
        $adminEmail  = $data['admin_email']  ?? '';
        $adminPass   = $data['admin_password'] ?? '';

        // Construir whatsapp_config desde los campos
        $waEmail   = $data['whatsapp_email']   ?? '';
        $waPass    = $data['whatsapp_password'] ?? '';
        $waApi     = $data['whatsapp_api_base_url'] ?? '';
        $waConnIds = $data['whatsapp_connection_ids'] ?? '';

        unset(
            $data['crear_admin_inicial'],
            $data['admin_nombre'],
            $data['admin_email'],
            $data['admin_password'],
            $data['whatsapp_email'],
            $data['whatsapp_password'],
            $data['whatsapp_api_base_url'],
            $data['whatsapp_connection_ids'],
            $data['logo_archivo']
        );

        // Subida del logo (si vino archivo nuevo)
        if ($this->logo_archivo) {
            $slug = $data['slug'] ?? ($this->slug ?: 'tenant-' . ($this->editandoId ?? 'new'));
            $ext  = $this->logo_archivo->getClientOriginalExtension();
            $path = $this->logo_archivo->storeAs(
                'tenants/logos',
                "{$slug}-" . time() . ".{$ext}",
                'public'
            );
            // URL pública /storage/tenants/logos/...
            $data['logo_url'] = '/storage/' . $path;

            // Borrar logo anterior si existía
            if ($this->logo_url_actual && str_starts_with($this->logo_url_actual, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $this->logo_url_actual);
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Solo guardar whatsapp_config si hay datos
        if ($waEmail || $waPass || $waConnIds) {
            $ids = collect(explode(',', $waConnIds))
                ->map(fn($x) => (int) trim($x))
                ->filter()
                ->values()
                ->all();

            $data['whatsapp_config'] = array_filter([
                'email'          => $waEmail ?: null,
                'password'       => $waPass ?: null,
                'api_base_url'   => $waApi ?: null,
                'connection_ids' => $ids ?: null,
            ]);
        }

        $tenant = Tenant::updateOrCreate(['id' => $this->editandoId], $data);

        // Limpiar caché de mapeos de WhatsApp
        app(\App\Services\WhatsappResolverService::class)->limpiarCache();

        // Crear admin inicial si solicitado y es nuevo tenant
        if ($crear && !$this->editandoId) {
            $u = User::create([
                'name'      => $adminNombre,
                'email'     => $adminEmail,
                'password'  => Hash::make($adminPass),
                'tenant_id' => $tenant->id,
                'activo'    => true,
            ]);
            $u->assignRole('admin');
        }

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId
                ? "✓ Tenant '{$tenant->nombre}' actualizado."
                : "🎉 Tenant '{$tenant->nombre}' creado." . ($crear ? " Admin inicial: {$adminEmail}" : ''),
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $t = Tenant::find($id);
        if (!$t) return;
        $t->activo = !$t->activo;
        $t->save();
    }

    public function impersonar(int $id): void
    {
        $t = Tenant::find($id);
        if (!$t) return;

        session(['tenant_imitado_id' => $t->id]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "🎭 Estás viendo la plataforma como '{$t->nombre}'. Recarga las páginas para ver sus datos.",
        ]);

        $this->redirect('/pedidos');
    }

    /**
     * Abre el modal y dispara el setup del subdominio (DNS + Nginx + SSL).
     */
    public function configurarSubdominio(int $id): void
    {
        $tenant = app(TenantManager::class)->withoutTenant(fn() => Tenant::find($id));
        if (!$tenant) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Tenant no encontrado.']);
            return;
        }

        $base = config('app.tenant_base_domain', 'tecnobyte360.com');

        $this->subdomModalAbierto = true;
        $this->subdomTenantId     = $tenant->id;
        $this->subdomTenantNombre = $tenant->nombre;
        $this->subdomTenantSlug   = $tenant->slug;
        $this->subdomDominio      = "{$tenant->slug}.{$base}";
        $this->subdomCorriendo    = true;
        $this->subdomExito        = false;
        $this->subdomEstado       = 'pendiente';
        $this->subdomIniciadoTs   = time();
        $this->subdomLog          = "[1/3] Iniciando setup para {$this->subdomDominio}...\n";

        try {
            $exit = Artisan::call('tenants:setup-subdominio', [
                'slug'   => $tenant->slug,
                '--wait' => 30,
            ]);
            $this->subdomLog .= Artisan::output();

            if ($exit !== 0) {
                $this->subdomExito     = false;
                $this->subdomCorriendo = false;
                $this->subdomEstado    = 'error';
                return;
            }

            // Laravel terminó OK → ahora esperamos al watcher del host
            $this->subdomLog .= "\n[2/3] Esperando al watcher del host (Nginx + certbot)...\n";
            $this->subdomLog .= "      Polling cada 2s del estado...\n";
        } catch (\Throwable $e) {
            $this->subdomLog .= "\n❌ Excepción: " . $e->getMessage();
            $this->subdomExito     = false;
            $this->subdomCorriendo = false;
            $this->subdomEstado    = 'error';
        }
    }

    /**
     * Polling llamado desde wire:poll en el modal cada 2s.
     * Detecta cuando el watcher del host renombra el .pending a .done o .error.
     */
    public function chequearEstadoSubdominio(): void
    {
        if (!$this->subdomCorriendo || !$this->subdomDominio) return;

        $base = storage_path('app/nginx-tenants');
        $pending = "{$base}/{$this->subdomDominio}.conf.pending";
        $done    = "{$base}/{$this->subdomDominio}.conf.pending.done";
        $error   = "{$base}/{$this->subdomDominio}.conf.pending.error";

        if (file_exists($done)) {
            $this->subdomCorriendo = false;
            $this->subdomExito     = true;
            $this->subdomEstado    = 'aplicado';
            $this->subdomLog .= "\n[3/3] ✅ El watcher del host aplicó Nginx + SSL correctamente.\n";
            $this->subdomLog .= "       Sitio operativo: https://{$this->subdomDominio}\n";
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✅ Subdominio operativo: https://{$this->subdomDominio}",
            ]);
            return;
        }

        if (file_exists($error)) {
            $this->subdomCorriendo = false;
            $this->subdomExito     = false;
            $this->subdomEstado    = 'error';
            $this->subdomLog .= "\n❌ El watcher del host marcó error. Revisa /var/log/aplicar-tenant.log en el VPS.\n";
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => "❌ Falló al aplicar Nginx. Revisa logs del host.",
            ]);
            return;
        }

        // Aún pending → seguimos esperando
        if (file_exists($pending)) {
            $espera = time() - ($this->subdomIniciadoTs ?? time());
            // Timeout suave de 3 minutos
            if ($espera > 180) {
                $this->subdomCorriendo = false;
                $this->subdomExito     = false;
                $this->subdomEstado    = 'error';
                $this->subdomLog .= "\n⏱️ Timeout: el watcher del host no respondió en 3 min.\n";
                $this->subdomLog .= "   ¿Está corriendo el servicio?  sudo systemctl status tenant-subdomain-watcher\n";
                return;
            }
        }
    }

    /**
     * Prueba la OpenAI API key que está en el formulario.
     * Hace un request mínimo a /v1/models — si la key es válida, responde OK.
     */
    public function probarOpenaiKey(): void
    {
        $key = trim($this->openai_api_key);
        if ($key === '') {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => '⚠️ Deja el campo vacío para usar la key global, o pega una key para probarla.',
            ]);
            return;
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($key)
                ->timeout(15)
                ->get('https://api.openai.com/v1/models');

            if ($resp->successful()) {
                $count = is_array($resp->json('data')) ? count($resp->json('data')) : 0;
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => "✓ Key válida. OpenAI expone {$count} modelos disponibles.",
                ]);
            } else {
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => "❌ Key inválida ({$resp->status()}): " . ($resp->json('error.message') ?: $resp->body()),
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function probarHostinger(): void
    {
        try {
            $svc = app(HostingerDnsService::class);
            $registros = $svc->listarRegistros();
            $count = is_array($registros) ? count($registros) : 0;
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✓ Conexión OK con Hostinger. {$count} registros DNS en la zona.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Hostinger: ' . $e->getMessage(),
            ]);
        }
    }

    public function cerrarSubdomModal(): void
    {
        $this->subdomModalAbierto = false;
        $this->subdomLog          = '';
        $this->subdomTenantId     = null;
    }

    // ─────────────────────────────────────────────────────────────────
    // 🗑️  ELIMINACIÓN DEFINITIVA (con doble confirmación por nombre)
    // ─────────────────────────────────────────────────────────────────
    public function abrirModalEliminar(int $id): void
    {
        $t = app(TenantManager::class)->withoutTenant(fn () => Tenant::find($id));
        if (!$t) return;

        $this->eliminarModalAbierto = true;
        $this->eliminarTenantId     = $t->id;
        $this->eliminarTenantNombre = $t->nombre;
        $this->eliminarTenantSlug   = $t->slug;
        $this->eliminarConfirmacion = '';
        $this->eliminarCorriendo    = false;
        $this->eliminarLog          = '';
    }

    public function cerrarModalEliminar(): void
    {
        $this->eliminarModalAbierto = false;
        $this->eliminarTenantId     = null;
        $this->eliminarTenantNombre = '';
        $this->eliminarTenantSlug   = '';
        $this->eliminarConfirmacion = '';
        $this->eliminarCorriendo    = false;
        $this->eliminarLog          = '';
    }

    /**
     * Ejecuta la eliminación definitiva.
     * Requiere que el super-admin haya tipeado EXACTAMENTE el nombre del tenant.
     */
    public function confirmarEliminacion(): void
    {
        if (!$this->eliminarTenantId) return;

        // Validar que tipeó el nombre exacto (sensible a mayúsculas para máxima seguridad)
        if (trim($this->eliminarConfirmacion) !== trim($this->eliminarTenantNombre)) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⛔ El texto no coincide con el nombre del tenant. Tipea exactamente: ' . $this->eliminarTenantNombre,
            ]);
            return;
        }

        $this->eliminarCorriendo = true;
        $this->eliminarLog = "Iniciando eliminación de '{$this->eliminarTenantNombre}'...\n\n";

        try {
            $exit = Artisan::call('tenants:eliminar-definitivo', [
                'slug'    => $this->eliminarTenantSlug,
                '--force' => true,
            ]);
            $this->eliminarLog .= Artisan::output();
            $this->eliminarLog .= "\n" . ($exit === 0 ? '✅ Eliminación exitosa.' : '❌ Hubo errores. Revisa el log.');

            if ($exit === 0) {
                $this->dispatch('notify', [
                    'type'    => 'success',
                    'message' => "🗑️  Tenant '{$this->eliminarTenantNombre}' eliminado definitivamente.",
                ]);
            }
        } catch (\Throwable $e) {
            $this->eliminarLog .= "\n❌ Excepción: " . $e->getMessage();
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ Error: ' . $e->getMessage(),
            ]);
        }

        $this->eliminarCorriendo = false;
    }

    public function dejarImpersonar(): void
    {
        session()->forget('tenant_imitado_id');
        $this->dispatch('notify', ['type' => 'info', 'message' => '✓ Volviste al modo super-admin global.']);
        $this->redirect(route('admin.tenants.index'));
    }

    private function resetCampos(): void
    {
        $this->editandoId           = null;
        $this->nombre               = '';
        $this->slug                 = '';
        $this->plan                 = Tenant::PLAN_BASICO;
        $this->activo               = true;
        $this->contacto_nombre      = '';
        $this->contacto_email       = '';
        $this->contacto_telefono    = '';
        $this->color_primario       = '#d68643';
        $this->color_secundario     = '#a85f24';
        $this->logo_url_actual      = null;
        $this->logo_archivo         = null;
        $this->openai_api_key       = '';
        $this->trial_ends_at        = null;
        $this->subscription_ends_at = null;
        $this->notas_internas       = '';
        $this->whatsapp_email           = '';
        $this->whatsapp_password        = '';
        $this->whatsapp_api_base_url    = 'https://wa-api.tecnobyteapp.com:1422';
        $this->whatsapp_connection_ids  = '';
        $this->crear_admin_inicial  = true;
        $this->admin_nombre         = '';
        $this->admin_email          = '';
        $this->admin_password       = '';
        $this->resetValidation();
    }

    public function render()
    {
        // Saltar el global scope (super-admin ve todos los tenants)
        $tenants = app(TenantManager::class)->withoutTenant(function () {
            return Tenant::withCount(['users', 'pedidos', 'clientes'])
                ->when($this->busqueda, fn($q) => $q->where(
                    fn($qq) =>
                    $qq->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('slug', 'like', "%{$this->busqueda}%")
                        ->orWhere('contacto_email', 'like', "%{$this->busqueda}%")
                ))
                ->orderByDesc('id')
                ->paginate(15);
        });

        $kpis = app(TenantManager::class)->withoutTenant(function () {
            return [
                'total'    => Tenant::count(),
                'activos'  => Tenant::where('activo', true)->count(),
                'trial'    => Tenant::whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now())->count(),
                'vencidos' => Tenant::whereNotNull('subscription_ends_at')
                    ->where('subscription_ends_at', '<', now())
                    ->where(fn($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', now()))
                    ->count(),
            ];
        });

        return view('livewire.admin.tenants.index', [
            'tenants'         => $tenants,
            'kpis'            => $kpis,
            'tenantImitadoId' => session('tenant_imitado_id'),
        ])->layout('layouts.app');
    }
}
