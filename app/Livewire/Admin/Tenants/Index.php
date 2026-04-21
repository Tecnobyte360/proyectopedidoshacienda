<?php

namespace App\Livewire\Admin\Tenants;

use App\Models\Tenant;
use App\Models\User;
use App\Services\HostingerDnsService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    // Subdominio: log de salida del comando
    public bool   $subdomModalAbierto = false;
    public ?int   $subdomTenantId     = null;
    public string $subdomTenantNombre = '';
    public string $subdomLog          = '';
    public bool   $subdomCorriendo    = false;
    public bool   $subdomExito        = false;

    public string $nombre              = '';
    public string $slug                = '';
    public string $plan                = Tenant::PLAN_BASICO;
    public bool   $activo              = true;
    public string $contacto_nombre     = '';
    public string $contacto_email      = '';
    public string $contacto_telefono   = '';
    public string $color_primario      = '#d68643';
    public string $color_secundario    = '#a85f24';
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

    protected function rules(): array
    {
        return [
            'nombre'              => 'required|string|max:150',
            'slug'                => [
                'nullable',
                'string',
                'max:80',
                // Solo a-z, 0-9 y guion medio. NO _ . espacios MAYÚSC.
                // (Let's Encrypt rechaza otros caracteres en subdominios.)
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'unique:tenants,slug,' . ($this->editandoId ?? 'NULL'),
            ],
            'plan'                => 'required|in:basico,pro,empresa',
            'activo'              => 'boolean',
            'contacto_nombre'     => 'nullable|string|max:120',
            'contacto_email'      => 'nullable|email|max:150',
            'contacto_telefono'   => 'nullable|string|max:30',
            'color_primario'      => 'nullable|string|max:10',
            'color_secundario'    => 'nullable|string|max:10',
            'trial_ends_at'       => 'nullable|date',
            'subscription_ends_at'=> 'nullable|date',
            'notas_internas'      => 'nullable|string|max:2000',

            'whatsapp_email'         => 'nullable|email|max:150',
            'whatsapp_password'      => 'nullable|string|max:150',
            'whatsapp_api_base_url'  => 'nullable|string|max:200',
            'whatsapp_connection_ids'=> 'nullable|string|max:500',

            'crear_admin_inicial' => 'boolean',
            'admin_nombre'        => 'required_if:crear_admin_inicial,true|nullable|string|max:120',
            'admin_email'         => 'required_if:crear_admin_inicial,true|nullable|email|max:150|unique:users,email',
            'admin_password'      => 'required_if:crear_admin_inicial,true|nullable|string|min:6',
        ];
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
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
        // Normalizar antes de validar para que la regex no falle por mayúsculas/espacios
        if ($this->slug !== '') {
            $this->slug = Tenant::normalizarSlug($this->slug);
        }

        $data = $this->validate();

        $crear = $data['crear_admin_inicial'] ?? false;
        $adminNombre = $data['admin_nombre'] ?? '';
        $adminEmail  = $data['admin_email']  ?? '';
        $adminPass   = $data['admin_password']?? '';

        // Construir whatsapp_config desde los campos
        $waEmail   = $data['whatsapp_email']   ?? '';
        $waPass    = $data['whatsapp_password'] ?? '';
        $waApi     = $data['whatsapp_api_base_url'] ?? '';
        $waConnIds = $data['whatsapp_connection_ids'] ?? '';

        unset(
            $data['crear_admin_inicial'], $data['admin_nombre'],
            $data['admin_email'], $data['admin_password'],
            $data['whatsapp_email'], $data['whatsapp_password'],
            $data['whatsapp_api_base_url'], $data['whatsapp_connection_ids']
        );

        // Solo guardar whatsapp_config si hay datos
        if ($waEmail || $waPass || $waConnIds) {
            $ids = collect(explode(',', $waConnIds))
                ->map(fn ($x) => (int) trim($x))
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
        $tenant = app(TenantManager::class)->withoutTenant(fn () => Tenant::find($id));
        if (!$tenant) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Tenant no encontrado.']);
            return;
        }

        $this->subdomModalAbierto = true;
        $this->subdomTenantId     = $tenant->id;
        $this->subdomTenantNombre = $tenant->nombre;
        $this->subdomCorriendo    = true;
        $this->subdomExito        = false;
        $this->subdomLog          = "Iniciando setup para {$tenant->slug}.tecnobyte360.com...\n";

        try {
            $exit = Artisan::call('tenants:setup-subdominio', [
                'slug'   => $tenant->slug,
                '--wait' => 30,
            ]);
            $this->subdomLog .= Artisan::output();
            $this->subdomExito = ($exit === 0);
        } catch (\Throwable $e) {
            $this->subdomLog .= "\n❌ Excepción: " . $e->getMessage();
            $this->subdomExito = false;
        }

        $this->subdomCorriendo = false;

        $this->dispatch('notify', [
            'type'    => $this->subdomExito ? 'success' : 'error',
            'message' => $this->subdomExito
                ? "✅ Subdominio configurado: https://{$tenant->slug}.tecnobyte360.com"
                : "❌ Hubo errores configurando el subdominio. Revisa el log.",
        ]);
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
                ->when($this->busqueda, fn ($q) => $q->where(fn ($qq) =>
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
                    ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', now()))
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
