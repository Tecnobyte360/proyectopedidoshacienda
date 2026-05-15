<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Multi-tenant en User:
 *  - NO usamos BelongsToTenant aquí porque rompe el login del super-admin
 *    desde subdominios (el scope filtraría users.tenant_id=X y excluiría
 *    a los super-admins con tenant_id=NULL).
 *  - El filtrado se hace MANUALMENTE en cada componente Livewire que liste
 *    usuarios (ej: Usuarios\Index, Roles\Index) usando el TenantManager.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'tenant_id',
        'sede_id',
        'activo',
        'ultimo_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
            'ultimo_login_at'   => 'datetime',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function iniciales(): string
    {
        return collect(explode(' ', trim($this->name ?? '')))
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }

    public function rolPrincipal(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Departamentos a los que pertenece el usuario.
     * Cuando el bot deriva una conversación a un departamento, solo los
     * usuarios asignados a ese departamento verán el chat (vía filtro
     * en Chat\Index). Usuarios sin departamentos ven TODOS.
     */
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_user')
            ->withTimestamps();
    }

    /**
     * IDs de departamentos del usuario (cacheado en la request).
     */
    public function departamentoIds(): array
    {
        return $this->departamentos()->pluck('departamentos.id')->all();
    }

    /**
     * ¿Puede ver TODAS las conversaciones del tenant, sin importar departamento?
     * Aplica si:
     *  - Es super-admin, O
     *  - Tiene permiso `chat.ver-todos`, O
     *  - No tiene ningún departamento asignado (típicamente admins locales).
     */
    public function puedeVerTodasLasConversaciones(): bool
    {
        if ($this->isSuperAdmin()) return true;
        if ($this->can('chat.ver-todos')) return true;
        return $this->departamentos()->count() === 0;
    }

    /**
     * Super-admin: usuario sin tenant_id (tú, dueño del producto).
     * Ve todos los tenants y puede cambiarse entre ellos.
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('super-admin');
    }
}
