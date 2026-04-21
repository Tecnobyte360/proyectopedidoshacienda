<?php

namespace App\Livewire\Roles;

use Database\Seeders\RolesPermisosSeeder;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string $name        = '';
    public array  $permisosSel = [];

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:60|unique:roles,name,' . ($this->editandoId ?? 'NULL'),
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
            // Quitar todos
            $this->permisosSel = array_values(array_diff($this->permisosSel, $permisosModulo));
        } else {
            // Agregar todos
            $this->permisosSel = array_values(array_unique(array_merge($this->permisosSel, $permisosModulo)));
        }
    }

    public function guardar(): void
    {
        $data = $this->validate();

        $rol = Role::updateOrCreate(['id' => $this->editandoId], ['name' => $data['name'], 'guard_name' => 'web']);
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

    private function resetCampos(): void
    {
        $this->editandoId  = null;
        $this->name        = '';
        $this->permisosSel = [];
        $this->resetValidation();
    }

    public function render()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();

        return view('livewire.roles.index', [
            'roles'           => $roles,
            'permisosPorMod'  => RolesPermisosSeeder::PERMISOS,
        ])->layout('layouts.app');
    }
}
