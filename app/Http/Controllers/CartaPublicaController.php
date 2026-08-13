<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * 🥩 Carta pública (catálogo) de un tenant, pensada para abrirse DENTRO del
 * navegador interno de WhatsApp desde un botón CTA URL que envía el bot.
 *
 * El cliente arma su carrito 100% en el navegador y al tocar "Enviar pedido"
 * se construye un deep-link wa.me hacia el número del negocio con el pedido
 * ya redactado. La IA lo recibe como texto y sigue su flujo normal.
 *
 * Ruta pública (sin auth): /carta/{slug}
 */
class CartaPublicaController extends Controller
{
    /** Número de WhatsApp (wa.me) por tenant. Fallback mientras no haya columna. */
    private const WA_NUMEROS = [
        'la-hacienda' => '573146153567',
    ];

    /** Logo específico para la carta (tiene prioridad sobre tenant->logo_url). */
    private const LOGOS = [
        'la-hacienda' => 'https://kivox.co/carta-fotos/la-hacienda-logo.jpg',
    ];

    /** Colores de la carta [primario, secundario] — override sobre el branding. */
    private const COLORS = [
        'la-hacienda' => ['#F47920', '#C25E12'], // naranja + negro (como el logo)
    ];

    public function show(string $slug)
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('activo', true)
            ->first();

        abort_unless($tenant, 404, 'Catálogo no disponible.');

        // Categorías del tenant (solo las que tienen productos activos)
        $categorias = DB::table('productos_categorias as c')
            ->join('productos as p', 'p.categoria_id', '=', 'c.id')
            ->where('c.tenant_id', $tenant->id)
            ->where('p.activo', true)
            ->select('c.id', 'c.nombre', DB::raw('COUNT(p.id) as n'))
            ->groupBy('c.id', 'c.nombre')
            ->orderByDesc('n')
            ->get();

        // Productos activos (precio > 0 para no mostrar items sin precio)
        $productos = DB::table('productos')
            ->where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->where('precio_base', '>', 0)
            ->select('id', 'categoria_id', 'nombre', 'descripcion_corta', 'unidad', 'precio_base', 'destacado', 'imagen_url')
            ->orderByDesc('destacado')
            ->orderBy('nombre')
            ->get();

        // Estructura JS: categorías + productos
        $cats = $categorias->map(fn ($c) => [
            'id'  => (int) $c->id,
            'n'   => $c->nombre,
            'cnt' => (int) $c->n,
        ])->values();

        $prods = $productos->map(fn ($p) => [
            'id'  => (int) $p->id,
            'cid' => (int) $p->categoria_id,
            'n'   => $p->nombre,
            'ds'  => $p->descripcion_corta ? mb_substr($p->descripcion_corta, 0, 60) : null,
            'u'   => $p->unidad ?: 'und',
            'pr'  => (float) $p->precio_base,
            'd'   => (bool) $p->destacado,
            'img' => $p->imagen_url ?: null,
        ])->values();

        $waNumero = self::WA_NUMEROS[$slug] ?? null;

        // Logo: primero el específico de carta, si no el del branding del tenant.
        $logoUrl = self::LOGOS[$slug] ?? null;
        if (!$logoUrl && $tenant->logo_url) {
            $logoUrl = str_starts_with($tenant->logo_url, 'http')
                ? $tenant->logo_url
                : 'https://kivox.co' . $tenant->logo_url;
        }

        [$cPrim, $cSec] = self::COLORS[$slug]
            ?? [$tenant->color_primario ?: '#c1471f', $tenant->color_secundario ?: '#a3391a'];

        return view('carta-publica', [
            'tenant'      => $tenant,
            'categorias'  => $cats,
            'productos'   => $prods,
            'waNumero'    => $waNumero,
            'logoUrl'     => $logoUrl,
            'colorPrim'   => $cPrim,
            'colorSec'    => $cSec,
        ]);
    }
}
