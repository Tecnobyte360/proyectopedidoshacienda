<?php

namespace App\Models\Scopes;

use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra automáticamente cualquier query Eloquent por el tenant actual.
 * Si no hay tenant (CLI, super-admin, jobs), no filtra.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(TenantManager::class);

        // Si está en modo "bypass" (super-admin global), no filtra
        if ($manager->isBypassed()) {
            return;
        }

        $tenantId = $manager->id();
        if ($tenantId === null) {
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $tenantId);
    }
}
