<?php

namespace App\Services\Meta;

use App\Models\MetaWhatsappConfig;
use App\Models\Producto;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * 🛒⚡ Sincroniza productos de KIVOX con el catálogo de Meta EN TIEMPO REAL
 * usando la Catalog Batch API (items_batch). Se dispara cuando un producto se
 * crea/edita/elimina, para que aparezca en el catálogo de WhatsApp sin esperar
 * el refresco por hora del feed.
 *
 * Requiere un `catalog_token` (System User con permiso catalog_management) en la
 * config Meta del tenant. Si no hay token, no hace nada (el feed horario cubre).
 */
class MetaCatalogSyncService
{
    /** Sincroniza (CREATE/UPDATE) un producto en el catálogo de Meta. */
    public function upsert(Producto $producto): bool
    {
        $sku = $producto->codigo ?: ('KVX-' . $producto->id);
        return $this->enviarBatch($producto->tenant_id, [
            [
                'method' => 'UPDATE', // UPDATE crea si no existe (upsert)
                'data'   => $this->itemData($producto),
            ],
        ], 'upsert', $sku);
    }

    /** Elimina un producto del catálogo de Meta por su SKU (id). */
    public function eliminar(int $tenantId, string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') return false;

        return $this->enviarBatch($tenantId, [
            [
                'method' => 'DELETE',
                'data'   => ['id' => $sku],
            ],
        ], 'delete', $sku);
    }

    /**
     * Construye el item en el formato de la Catalog Batch API.
     * price en centavos: Meta espera el precio con la moneda, ej "6000 COP".
     */
    private function itemData(Producto $producto): array
    {
        // Forzamos el dominio público para las URLs absolutas (igual que el feed).
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme('https');

        $tenant = Tenant::withoutGlobalScopes()->find($producto->tenant_id);
        $marca  = $tenant->nombre ?? 'Tienda';

        $imagen = $producto->urlImagen()
            ?: ($tenant->logo_url ?: 'https://placehold.co/600x600/6f4e37/ffffff/png?text=Producto');

        $descripcion = $producto->descripcion
            ?: ($producto->descripcion_corta ?: $producto->nombre);

        // ⚠️ items_batch usa el formato del FEED: id, title, price "MONTO COP",
        //    image_link, link (no retailer_id/name/image_url).
        return [
            'id'           => $producto->codigo ?: ('KVX-' . $producto->id),
            'title'        => mb_substr((string) $producto->nombre, 0, 200),
            'description'  => mb_substr((string) $descripcion, 0, 9999),
            'availability' => 'in stock',
            'condition'    => 'new',
            'price'        => number_format((float) $producto->precio_base, 2, '.', '') . ' COP',
            'link'         => url('/'),
            'image_link'   => $imagen,
            'brand'        => $marca,
        ];
    }

    /**
     * Ejecuta el items_batch contra Meta. Devuelve true si Meta aceptó.
     * Silencioso ante fallos (no debe romper el guardado del producto).
     */
    private function enviarBatch(int $tenantId, array $requests, string $accion, string $ref): bool
    {
        try {
            $cfg = MetaWhatsappConfig::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('activo', true)
                ->first();

            if (!$cfg || empty($cfg->catalog_id) || empty($cfg->catalog_token)) {
                return false; // sin token de catálogo → el feed horario se encarga
            }

            $api = $cfg->api_version ?: 'v25.0';
            $url = "https://graph.facebook.com/{$api}/{$cfg->catalog_id}/items_batch";

            $resp = Http::asJson()->timeout(20)->post($url, [
                'access_token' => $cfg->catalog_token,
                'item_type'    => 'PRODUCT_ITEM',
                'requests'     => $requests,
            ]);

            if ($resp->successful()) {
                Log::info('🛒⚡ Catálogo Meta sincronizado', [
                    'tenant_id' => $tenantId,
                    'accion'    => $accion,
                    'ref'       => $ref,
                    'handles'   => $resp->json('handles'),
                ]);
                return true;
            }

            Log::warning('🛒⚠️ Sync catálogo Meta falló', [
                'tenant_id' => $tenantId,
                'accion'    => $accion,
                'ref'       => $ref,
                'status'    => $resp->status(),
                'body'      => mb_substr($resp->body(), 0, 400),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('🛒🔴 Sync catálogo Meta excepción: ' . $e->getMessage());
            return false;
        }
    }
}
