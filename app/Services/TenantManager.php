<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Gestiona el "tenant actual" durante el request.
 * Es un singleton del container — se inyecta donde se necesite.
 *
 *   app(TenantManager::class)->current()  → Tenant|null
 *   app(TenantManager::class)->id()       → int|null (id del tenant)
 *   app(TenantManager::class)->set($t)    → setea el tenant
 *
 * El TenantScope global lo consulta en cada query Eloquent para filtrar
 * por tenant_id automáticamente.
 *
 * IMPORTANTE: si current() es null, las queries NO se filtran por tenant
 * (esto permite que comandos artisan, jobs, super-admin, etc. vean todo).
 */
class TenantManager
{
    protected ?Tenant $current = null;

    /** Cuando true, NUNCA filtra por tenant (super-admin global). */
    protected bool $bypassed = false;

    public function set(?Tenant $tenant): self
    {
        $this->current = $tenant;
        return $this;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }

    public function has(): bool
    {
        return $this->current !== null;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    /**
     * Ejecuta un callback "saltándose" el filtro de tenant temporalmente.
     * Útil para super-admin o comandos que necesitan ver/operar sobre todos
     * los tenants en una sola corrida (ej. limpieza global, reportes).
     */
    public function withoutTenant(callable $cb)
    {
        $previo = $this->bypassed;
        $this->bypassed = true;
        try {
            return $cb();
        } finally {
            $this->bypassed = $previo;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }
}
