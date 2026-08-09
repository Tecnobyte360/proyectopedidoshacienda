<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera el FEED de catálogo de productos en el formato que Meta
 * (Commerce Manager / WhatsApp) espera para importar productos vía
 * "Origen de datos → Feed programado".
 *
 * Formato: CSV UTF-8 con las columnas obligatorias de Meta:
 *   id, title, description, availability, condition, price, link, image_link, brand
 *
 * URL pública (sin auth): /catalogo-meta/{tenant}.csv
 */
class MetaCatalogFeedController extends Controller
{
    public function csv(Request $request, int $tenant): StreamedResponse
    {
        // Forzamos el dominio público (Meta lee este feed desde fuera): las URLs
        // de link/imagen deben ser absolutas y accesibles, nunca "localhost".
        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        \Illuminate\Support\Facades\URL::forceScheme('https');

        $tenantModel = Tenant::withoutGlobalScopes()->findOrFail($tenant);

        // Imagen por defecto para productos sin foto (Meta EXIGE image_link).
        // Preferimos el logo del tenant; si no hay, un placeholder estable.
        $imagenDefault = $tenantModel->logo_url
            ?: 'https://placehold.co/600x600/6f4e37/ffffff/png?text=Producto';
        $brand = $tenantModel->nombre ?? 'Tienda';

        $productos = Producto::withoutGlobalScopes()
            ->where('tenant_id', $tenant)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="catalogo-meta-' . $tenant . '.csv"',
            'Cache-Control'       => 'no-store, max-age=0',
        ];

        return response()->stream(function () use ($productos, $imagenDefault, $brand) {
            $out = fopen('php://output', 'w');

            // Columnas requeridas por Meta.
            fputcsv($out, [
                'id', 'title', 'description', 'availability',
                'condition', 'price', 'link', 'image_link', 'brand',
            ]);

            foreach ($productos as $p) {
                $sku = $p->codigo ?: ('KVX-' . $p->id);

                $descripcion = $p->descripcion
                    ?: ($p->descripcion_corta ?: $p->nombre);

                // Meta pide el precio como "MONTO MONEDA", ej "40000.00 COP".
                $precio = number_format((float) $p->precio_base, 2, '.', '') . ' COP';

                $imagen = $p->urlImagen() ?: $imagenDefault;

                // Link del producto (página de seguimiento/tienda). Meta lo exige;
                // usamos la home del tenant como destino genérico.
                $link = url('/');

                fputcsv($out, [
                    $sku,
                    mb_substr($p->nombre, 0, 200),
                    mb_substr($descripcion, 0, 9999),
                    'in stock',
                    'new',
                    $precio,
                    $link,
                    $imagen,
                    $brand,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}
