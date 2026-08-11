<?php

namespace App\Observers;

use App\Models\MetaWhatsappConfig;
use App\Models\Producto;
use App\Services\Meta\MetaCatalogSyncService;
use Illuminate\Support\Facades\Cache;

/**
 * 🛒⚡ Sincroniza cada cambio de producto con el catálogo de Meta en tiempo real.
 * Solo actúa en tenants que tengan catálogo nativo + catalog_token configurado.
 * Se ejecuta DESPUÉS de la respuesta (afterResponse) para no frenar la UI.
 */
class ProductoObserver
{
    public function saved(Producto $producto): void
    {
        if (!$this->tenantTieneCatalogo((int) $producto->tenant_id)) return;

        $id = $producto->id;
        dispatch(function () use ($id) {
            $p = Producto::withoutGlobalScopes()->find($id);
            if ($p) app(MetaCatalogSyncService::class)->upsert($p);
        })->afterResponse();
    }

    public function deleted(Producto $producto): void
    {
        $tenantId = (int) $producto->tenant_id;
        $sku      = (string) ($producto->codigo ?? '');
        if ($sku === '' || !$this->tenantTieneCatalogo($tenantId)) return;

        dispatch(function () use ($tenantId, $sku) {
            app(MetaCatalogSyncService::class)->eliminar($tenantId, $sku);
        })->afterResponse();
    }

    /** ¿El tenant tiene catálogo nativo + token? (cacheado 5 min). */
    private function tenantTieneCatalogo(int $tenantId): bool
    {
        if ($tenantId <= 0) return false;
        return Cache::remember("meta_cat_token_tenant_{$tenantId}", 300, function () use ($tenantId) {
            return MetaWhatsappConfig::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('activo', true)
                ->whereNotNull('catalog_id')->where('catalog_id', '!=', '')
                ->whereNotNull('catalog_token')->where('catalog_token', '!=', '')
                ->exists();
        });
    }
}
