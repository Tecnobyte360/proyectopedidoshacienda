<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agrega comportamiento multi-tenant a un modelo:
 *  1. Auto-asigna tenant_id al crear (si no viene seteado)
 *  2. Filtra todas las queries por el tenant actual (TenantScope)
 *  3. Relación belongsTo(Tenant)
 *
 * Uso:
 *
 *   class Pedido extends Model {
 *       use BelongsToTenant;
 *       ...
 *   }
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-asignar tenant_id al crear (si no viene)
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(TenantManager::class)->id();
            }
        });

        // Filtro global por tenant
        static::addGlobalScope(new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
