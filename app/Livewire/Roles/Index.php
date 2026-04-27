<?php

namespace App\Livewire\Roles;

use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string $name        = '';
    public string $descripcion = '';
    public array  $permisosSel = [];

    /** ID del tenant del usuario logueado (para scope de roles propios) */
    private function tenantActualId(): ?int
    {
        return app(\App\Services\TenantManager::class)->id();
    }

    /** ¿El usuario actual es super-admin? (puede editar roles globales) */
    private function esSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:60',
            'descripcion' => 'nullable|string|max:255',
            'permisosSel' => 'array',
        ];
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $rol = Role::with('permissions')->findOrFail($id);

        // Verificar que el usuario puede editar este rol
        if (!$this->puedeEditar($rol)) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'No puedes editar roles del sistema. Crea uno propio para tu empresa.',
            ]);
            return;
        }

        $this->editandoId  = $rol->id;
        $this->name        = $rol->name;
        $this->permisosSel = $rol->permissions->pluck('name')->all();

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function toggleModulo(string $modulo): void
    {
        $permisosModulo = RolesPermisosSeeder::PERMISOS[$modulo] ?? [];
        $todosSeleccionados = !array_diff($permisosModulo, $this->permisosSel);

        if ($todosSeleccionados) {
            $this->permisosSel = array_values(array_diff($this->permisosSel, $permisosModulo));
        } else {
            $this->permisosSel = array_values(array_unique(array_merge($this->permisosSel, $permisosModulo)));
        }
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $tenantId = $this->tenantActualId();

        if ($this->editandoId) {
            $rol = Role::findOrFail($this->editandoId);
            if (!$this->puedeEditar($rol)) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar este rol.']);
                return;
            }
            $rol->update(['name' => $data['name']]);
        } else {
            // Validar nombre único en el scope del tenant (puede repetirse entre tenants)
            $existe = Role::where('name', $data['name'])
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->exists();
            if ($existe) {
                $this->addError('name', 'Ya existe un rol con ese nombre.');
                return;
            }

            // Si NO es super-admin, asignar tenant_id automáticamente
            $tenantIdParaCrear = $this->esSuperAdmin() ? null : $tenantId;

            $rol = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'tenant_id' => $tenantIdParaCrear,
            ]);
        }

        $rol->syncPermissions($this->permisosSel);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Rol actualizado.' : 'Rol creado.',
        ]);
    }

    public function eliminar(int $id): void
    {
        $rol = Role::find($id);
        if (!$rol) return;

        if (!$this->puedeEditar($rol)) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'No puedes eliminar un rol del sistema.',
            ]);
            return;
        }

        if ($rol->users()->count() > 0) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "No se puede eliminar — el rol '{$rol->name}' tiene usuarios asignados.",
            ]);
            return;
        }

        $rol->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Rol eliminado.']);
    }

    /**
     * Clona un rol global como rol propio del tenant.
     * Útil para que el admin tome un rol del sistema y lo personalice.
     */
    public function clonar(int $id): void
    {
        $base = Role::with('permissions')->find($id);
        if (!$base) return;

        $tenantId = $this->tenantActualId();
        if (!$tenantId && !$this->esSuperAdmin()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo identificar el tenant.']);
            return;
        }

        $nuevoNombre = $base->name . ' (' . ($tenantId ? 'tenant ' . $tenantId : 'copia') . ')';
        $i = 1;
        while (Role::where('name', $nuevoNombre)->exists()) {
            $i++;
            $nuevoNombre = $base->name . ' (copia ' . $i . ')';
        }

        $clon = Role::create([
            'name' => $nuevoNombre,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $clon->syncPermissions($base->permissions->pluck('name')->all());

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✅ Rol '{$nuevoNombre}' creado a partir de '{$base->name}'. Ahora puedes editarlo.",
        ]);
    }

    /**
     * El usuario puede editar si:
     * - Es super-admin (edita cualquier rol)
     * - O el rol pertenece a SU tenant (no es global)
     */
    private function puedeEditar(Role $rol): bool
    {
        if ($this->esSuperAdmin()) return true;
        $tenantId = $this->tenantActualId();
        return $rol->tenant_id !== null && (int) $rol->tenant_id === (int) $tenantId;
    }

    private function resetCampos(): void
    {
        $this->editandoId  = null;
        $this->name        = '';
        $this->descripcion = '';
        $this->permisosSel = [];
        $this->resetValidation();
    }

    public function render()
    {
        $tenantId = $this->tenantActualId();
        $esSuper  = $this->esSuperAdmin();

        // Mostrar: roles globales (tenant_id NULL) + los del tenant actual
        $rolesQuery = Role::with('permissions')->withCount('users')
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->orderByRaw('tenant_id IS NULL DESC') // globales primero
            ->orderBy('name');

        $roles = $rolesQuery->get();

        // Marcar cada rol con flag editable
        $roles->each(function ($r) use ($tenantId, $esSuper) {
            $r->es_global = $r->tenant_id === null;
            $r->es_editable = $esSuper || ($r->tenant_id !== null && (int) $r->tenant_id === (int) $tenantId);
        });

        return view('livewire.roles.index', [
            'roles'           => $roles,
            'permisosPorMod'  => RolesPermisosSeeder::PERMISOS,
            'esSuperAdmin'    => $esSuper,
        ])->layout('layouts.app');
    }
}
