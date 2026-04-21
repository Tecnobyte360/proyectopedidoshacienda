<?php

namespace App\Livewire\Usuarios;

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
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|unique:users,email,' . ($this->editandoId ?? 'NULL'),
            'telefono' => 'nullable|string|max:30',
            'sede_id'  => 'nullable|exists:sedes,id',
            'password' => $this->editandoId ? 'nullable|string|min:6' : 'required|string|min:6',
            'rol'      => 'required|exists:roles,name',
            'activo'   => 'boolean',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $rol  = $data['rol'];
        unset($data['rol']);

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
        $user->syncRoles([$rol]);

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Usuario actualizado.' : 'Usuario creado.',
        ]);
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
        $usuarios = $this->aplicarFiltroTenant(User::with(['roles', 'sede']))
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

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'roles'    => Role::orderBy('name')->get(),
            'sedes'    => Sede::orderBy('nombre')->get(),
            'kpis'     => $kpis,
        ])->layout('layouts.app');
    }
}
