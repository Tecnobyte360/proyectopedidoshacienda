<?php

namespace App\Livewire\Usuarios;

use App\Models\Departamento;
use App\Models\Sede;
use App\Models\User;
use App\Services\TenantManager;
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

    public string $name      = '';
    public string $email     = '';
    public string $telefono  = '';
    public ?int   $sede_id   = null;
    public string $password  = '';
    public string $rol       = '';
    public bool   $activo    = true;
    /** Si está activo, el usuario ve datos de TODAS las sedes (no solo la suya). */
    public bool   $veTodasSedes = false;
    /** @var int[] IDs de departamentos seleccionados para el usuario */
    public array  $departamentos_ids = [];

    // Modal de reset de contraseña
    public bool   $modalResetAbierto   = false;
    public ?int   $resetUserId         = null;
    public string $resetUserNombre     = '';
    public string $resetUserEmail      = '';
    public string $resetPasswordNueva  = '';
    public string $resetModoPersonalizado = 'aleatoria'; // aleatoria | personalizada
    public string $resetPasswordCustom = '';

    public function updatingBusqueda(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        // Sólo permite editar users del mismo tenant
        $u = $this->aplicarFiltroTenant(User::with('roles'))->findOrFail($id);

        $this->editandoId = $u->id;
        $this->name       = $u->name;
        $this->email      = $u->email;
        $this->telefono   = (string) $u->telefono;
        $this->sede_id    = $u->sede_id;
        $this->activo     = (bool) $u->activo;
        $this->rol        = $u->roles->first()?->name ?? '';
        $this->password   = ''; // no precargar
        $this->departamentos_ids = $u->departamentos()->pluck('departamentos.id')->all();
        try { $this->veTodasSedes = $u->hasPermissionTo('sedes.ver-todas'); } catch (\Throwable $e) { $this->veTodasSedes = false; }

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    protected function rules(): array
    {
        return [
            'name'                => 'required|string|max:120',
            'email'               => 'required|email|unique:users,email,' . ($this->editandoId ?? 'NULL'),
            'telefono'            => 'nullable|string|max:30',
            'sede_id'             => 'nullable|exists:sedes,id',
            'password'            => $this->editandoId ? 'nullable|string|min:6' : 'required|string|min:6',
            'rol'                 => 'nullable|string|exists:roles,name',
            'activo'              => 'boolean',
            'departamentos_ids'   => 'array',
            'departamentos_ids.*' => 'integer|exists:departamentos,id',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $rol  = $data['rol'] ?? null;
        $deptos = $data['departamentos_ids'] ?? [];
        unset($data['rol'], $data['departamentos_ids']);

        // 🔒 Bloqueo de privilegio: super-admin solo se puede asignar
        // - desde el dominio principal
        // - sin impersonación activa
        // - por un super-admin real
        if ($rol === 'super-admin') {
            $u = auth()->user();
            $estaImpersonando = session()->has('tenant_imitado_id');
            $hostBase = config('app.tenant_base_domain', 'tecnobyte360.com');
            $host = request()->getHost();
            $reservados = ['www','api','admin','app','mail','pedidosonline'];
            $sub = ($host !== $hostBase && str_ends_with($host, '.' . $hostBase))
                ? strtolower(substr($host, 0, -strlen('.' . $hostBase)))
                : null;
            $enSubdominioTenant = $sub && !in_array($sub, $reservados, true);

            if (!$u?->hasRole('super-admin') || $estaImpersonando || $enSubdominioTenant) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => '⛔ No tienes permisos para asignar el rol Super-admin desde aquí.',
                ]);
                return;
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // 🔒 Multi-tenant: si NO estoy editando, asigno el tenant actual.
        // Así el admin de un tenant nunca puede crear users en otro tenant.
        if (!$this->editandoId) {
            $tenantId = app(TenantManager::class)->id();
            $data['tenant_id'] = $tenantId;  // null si es super-admin sin tenant
        }

        $user = User::updateOrCreate(['id' => $this->editandoId], $data);
        // Si rol es null/vacío → dejar al usuario sin ningún rol.
        // syncRoles([]) elimina cualquier rol previo.
        $user->syncRoles($rol ? [$rol] : []);

        // 🛡️ Sincronizar departamentos: solo permitir IDs del tenant actual
        // (defensa adicional contra IPC cross-tenant si alguien manipula el form).
        $tenantId = app(TenantManager::class)->id();
        $deptosValidos = $tenantId
            ? Departamento::where('tenant_id', $tenantId)->whereIn('id', $deptos)->pluck('id')->all()
            : [];
        $user->departamentos()->sync($deptosValidos);

        // 🏢 Permiso "ver todas las sedes" por usuario (directo, no por rol).
        try {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'sedes.ver-todas', 'guard_name' => 'web']);
            if ($this->veTodasSedes) {
                $user->givePermissionTo('sedes.ver-todas');
            } elseif ($user->hasPermissionTo('sedes.ver-todas')) {
                $user->revokePermissionTo('sedes.ver-todas');
            }
        } catch (\Throwable $e) { /* permiso no disponible */ }

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Usuario actualizado.' : 'Usuario creado.',
        ]);
    }

    /**
     * Abre el modal de reset de contraseña para un usuario del tenant.
     */
    public function abrirModalReset(int $id): void
    {
        $u = $this->aplicarFiltroTenant(User::query())->find($id);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Usuario no encontrado.']);
            return;
        }

        $this->resetUserId            = $u->id;
        $this->resetUserNombre        = $u->name;
        $this->resetUserEmail         = $u->email;
        $this->resetPasswordNueva     = '';
        $this->resetPasswordCustom    = '';
        $this->resetModoPersonalizado = 'aleatoria';
        $this->modalResetAbierto      = true;
    }

    public function cerrarModalReset(): void
    {
        $this->modalResetAbierto      = false;
        $this->resetUserId            = null;
        $this->resetUserNombre        = '';
        $this->resetUserEmail         = '';
        $this->resetPasswordNueva     = '';
        $this->resetPasswordCustom    = '';
    }

    /**
     * Aplica el reset según el modo elegido (aleatoria o personalizada).
     * Persiste la contraseña hasheada y muestra la nueva en plano UNA SOLA VEZ.
     */
    public function aplicarResetPassword(): void
    {
        if (!$this->resetUserId) return;

        $u = $this->aplicarFiltroTenant(User::query())->find($this->resetUserId);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Usuario no encontrado.']);
            return;
        }

        if ($this->resetModoPersonalizado === 'personalizada') {
            $custom = trim($this->resetPasswordCustom);
            if (mb_strlen($custom) < 6) {
                $this->dispatch('notify', ['type' => 'warning', 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
                return;
            }
            $nueva = $custom;
        } else {
            // Generar aleatoria: letras + números, fácil de leer (sin l, I, O, 0)
            $nueva = $this->generarPasswordSegura(10);
        }

        $u->password = Hash::make($nueva);
        $u->save();

        $this->resetPasswordNueva = $nueva;

        \Illuminate\Support\Facades\Log::info('🔐 Reset de contraseña', [
            'user_id'    => $u->id,
            'email'      => $u->email,
            'reseteado_por' => auth()->id(),
            'tenant_id'  => $u->tenant_id,
        ]);
    }

    private function generarPasswordSegura(int $longitud = 10): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin caracteres confusos
        $maxIdx = mb_strlen($chars) - 1;
        $resultado = '';
        for ($i = 0; $i < $longitud; $i++) {
            $resultado .= mb_substr($chars, random_int(0, $maxIdx), 1);
        }
        return $resultado;
    }

    public function toggleActivo(int $id): void
    {
        $u = $this->aplicarFiltroTenant(User::query())->find($id);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Usuario no encontrado o pertenece a otro tenant.']);
            return;
        }

        // No permitir desactivarse a sí mismo
        if ($u->id === auth()->id()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No puedes desactivar tu propia cuenta.']);
            return;
        }

        $u->activo = !$u->activo;
        $u->save();
    }

    /**
     * 🔐 Forzar a un usuario individual a activar 2FA en su próximo login.
     * Si ya tiene 2FA activo, en su lugar lo resetea (caso "perdió celular").
     */
    public function toggleForzar2fa(int $id): void
    {
        $u = $this->aplicarFiltroTenant(User::query())->find($id);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Usuario no encontrado.']);
            return;
        }

        // Si ya tiene 2FA → resetearlo (le quitamos el secret y queda obligado a re-activar)
        if ($u->tieneDosFactor()) {
            $u->update([
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'two_factor_enabled_at'     => null,
                'requiere_2fa'              => true,
                'requiere_2fa_desde'        => now(),
            ]);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "✓ 2FA de {$u->name} reseteado. Deberá volver a configurarlo en su próximo login.",
            ]);
            return;
        }

        // Toggle del flag de obligación individual
        if ($u->requiere_2fa) {
            $u->update(['requiere_2fa' => false, 'requiere_2fa_desde' => null]);
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "Ya no se le exige 2FA a {$u->name}.",
            ]);
        } else {
            $u->update(['requiere_2fa' => true, 'requiere_2fa_desde' => now()]);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "🔐 2FA exigido a {$u->name}. Al ingresar deberá configurarlo antes de usar la plataforma.",
            ]);
        }
    }

    /**
     * ⚡ Quita TODOS los roles a un usuario en un click.
     * Útil cuando un usuario va a quedar inactivo o necesitas revocarle acceso
     * sin eliminarlo. Quedará sin permisos pero podrá loguear (si activo=true).
     */
    public function quitarRol(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No puedes quitarte tus propios roles.']);
            return;
        }

        $u = $this->aplicarFiltroTenant(User::query())->find($id);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Usuario no encontrado.']);
            return;
        }

        $u->syncRoles([]);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "✓ Roles quitados a {$u->name}. Quedó sin permisos.",
        ]);
    }

    public function eliminar(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No puedes eliminar tu propia cuenta.']);
            return;
        }

        // Sólo permite borrar users del mismo tenant
        $u = $this->aplicarFiltroTenant(User::query())->find($id);
        if (!$u) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Usuario no encontrado o pertenece a otro tenant.']);
            return;
        }

        $u->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Usuario eliminado.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId = null;
        $this->name      = '';
        $this->email     = '';
        $this->telefono  = '';
        $this->sede_id   = null;
        $this->password  = '';
        $this->rol       = '';
        $this->activo    = true;
        $this->veTodasSedes = false;
        $this->departamentos_ids = [];
        $this->resetValidation();
    }

    /**
     * Aplica el filtro multi-tenant a una query de Users:
     *  - Si hay tenant activo (subdominio o impersonando) → filtra por tenant_id
     *  - Si no hay tenant (super-admin en dominio principal) → ve solo super-admins (tenant_id NULL)
     */
    private function aplicarFiltroTenant(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        $tenantId = app(TenantManager::class)->id();
        return $tenantId
            ? $q->where('users.tenant_id', $tenantId)
            : $q->whereNull('users.tenant_id');
    }

    public function render()
    {
        $usuarios = $this->aplicarFiltroTenant(User::with(['roles', 'sede', 'departamentos']))
            ->when($this->busqueda, function ($q) {
                $b = $this->busqueda;
                $q->where(fn ($qq) => $qq->where('name', 'like', "%{$b}%")
                    ->orWhere('email', 'like', "%{$b}%"));
            })
            ->orderBy('name')
            ->paginate(20);

        $kpis = [
            'total'    => $this->aplicarFiltroTenant(User::query())->count(),
            'activos'  => $this->aplicarFiltroTenant(User::query())->where('activo', true)->count(),
            'inactivos'=> $this->aplicarFiltroTenant(User::query())->where('activo', false)->count(),
            'admins'   => $this->aplicarFiltroTenant(User::role('admin'))->count(),
        ];

        // 🔒 Filtrar el rol "super-admin" del listado:
        // - Si el usuario NO es super-admin → ocultar.
        // - Si el super-admin está impersonando un tenant → ocultar igual
        //   (no debe poder asignar super-admin a un usuario de empresa).
        // - Si está en un subdominio de tenant → ocultar también.
        $u = auth()->user();
        $estaImpersonando = session()->has('tenant_imitado_id');
        $hostBase = config('app.tenant_base_domain', 'tecnobyte360.com');
        $host = request()->getHost();
        $reservados = ['www','api','admin','app','mail','pedidosonline'];
        $sub = ($host !== $hostBase && str_ends_with($host, '.' . $hostBase))
            ? strtolower(substr($host, 0, -strlen('.' . $hostBase)))
            : null;
        $enSubdominioTenant = $sub && !in_array($sub, $reservados, true);

        $puedeVerSuperAdmin = $u?->hasRole('super-admin')
            && !$estaImpersonando
            && !$enSubdominioTenant;

        // 🏢 Solo mostrar roles del tenant actual (o globales si super-admin en dominio principal).
        //    Antes traía Role::all() y como tras el bootstrap cada tenant tiene su propio
        //    rol 'admin', 'chat-only', etc → el dropdown mostraba 'Admin' 4 veces, etc.
        $tenantActualId = app(TenantManager::class)->id();
        $rolesQuery = Role::orderBy('name');
        if ($tenantActualId) {
            $rolesQuery->where('tenant_id', $tenantActualId);
        } else {
            $rolesQuery->whereNull('tenant_id');
        }
        if (!$puedeVerSuperAdmin) {
            $rolesQuery->where('name', '!=', 'super-admin');
        }

        return view('livewire.usuarios.index', [
            'usuarios'      => $usuarios,
            'roles'         => $rolesQuery->get(),
            'sedes'         => Sede::orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
            'kpis'          => $kpis,
        ])->layout('layouts.app');
    }
}
