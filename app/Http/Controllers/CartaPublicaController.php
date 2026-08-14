<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
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

    public function show(Request $request, string $slug)
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('activo', true)
            ->first();

        abort_unless($tenant, 404, 'Catálogo no disponible.');

        // La API key de Google Maps solo autoriza *.kivox.co (no el apex "kivox.co").
        // Si abren la carta en el apex, redirigimos al subdominio del tenant para
        // que el autocompletado de direcciones funcione.
        if ($request->getHost() === 'kivox.co') {
            return redirect()->away('https://' . $slug . '.kivox.co/carta/' . $slug);
        }

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
            ->select('id', 'categoria_id', 'nombre', 'descripcion_corta', 'unidad', 'precio_base', 'destacado', 'imagen_url', 'palabras_clave')
            ->orderByDesc('destacado')
            ->orderBy('nombre')
            ->get();

        // Estructura JS: categorías + productos
        $cats = $categorias->map(fn ($c) => [
            'id'  => (int) $c->id,
            'n'   => $c->nombre,
            'cnt' => (int) $c->n,
        ])->values();

        $prods = $productos->map(function ($p) {
            // 🔎 palabras_clave (sinónimos populares) → string para buscar en el front
            $kw = '';
            $arr = json_decode((string) $p->palabras_clave, true);
            if (is_array($arr)) $kw = mb_strtolower(implode(' ', $arr));
            return [
                'id'  => (int) $p->id,
                'cid' => (int) $p->categoria_id,
                'n'   => $p->nombre,
                'ds'  => $p->descripcion_corta ? mb_substr($p->descripcion_corta, 0, 60) : null,
                'u'   => $p->unidad ?: 'und',
                'pr'  => (float) $p->precio_base,
                'd'   => (bool) $p->destacado,
                'img' => $p->imagen_url ?: null,
                'kw'  => $kw,
            ];
        })->values();

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

        // Sedes activas (para "recoger en sede")
        $sedes = \App\Models\Sede::where('tenant_id', $tenant->id)
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'n' => $s->nombre, 'd' => $s->direccion])
            ->values();

        return view('carta-publica', [
            'tenant'      => $tenant,
            'categorias'  => $cats,
            'productos'   => $prods,
            'waNumero'    => $waNumero,
            'logoUrl'     => $logoUrl,
            'colorPrim'   => $cPrim,
            'colorSec'    => $cSec,
            'mapsKey'     => $tenant->google_maps_activo ? ($tenant->google_maps_api_key ?: null) : null,
            'sedes'       => $sedes,
        ]);
    }

    /**
     * 🔎 Verifica una cédula en el ERP (HGI/SGI) del tenant desde el checkout web.
     * Si existe, devuelve nombre/dirección/teléfono para pre-llenar.
     * Ruta pública con throttle — ver routes/web.php.
     */
    public function verificarCliente(Request $request, string $slug)
    {
        $tenant = $this->resolverTenant($slug);
        $cedula = preg_replace('/\D+/', '', (string) $request->input('cedula', ''));
        if (strlen($cedula) < 5) {
            return response()->json(['ok' => false, 'msg' => 'Cédula inválida.'], 422);
        }

        app(\App\Services\TenantManager::class)->set($tenant);

        try {
            $integ = \App\Models\Integracion::where('tenant_id', $tenant->id)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

            if (!$integ) {
                return response()->json(['ok' => true, 'existe' => false]);
            }

            $row = app(\App\Services\ClienteErpService::class)->buscar($integ, $cedula);
            if ($row) {
                return response()->json([
                    'ok'        => true,
                    'existe'    => true,
                    'nombre'    => trim((string) ($row['StrNombre'] ?? '')) ?: null,
                    'telefono'  => trim((string) ($row['StrCelular'] ?? '')) ?: null,
                    'direccion' => trim((string) ($row['StrDireccion'] ?? '')) ?: null,
                ]);
            }
            return response()->json(['ok' => true, 'existe' => false]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo consultar el ERP en este momento.'], 200);
        }
    }

    /**
     * 🗺️ Valida cobertura de una dirección (geocodifica con Google + polígonos de
     * sede) y devuelve si hay cobertura + costo de envío. Reusa la lógica del bot.
     */
    public function validarCobertura(Request $request, string $slug)
    {
        $tenant = $this->resolverTenant($slug);
        $direccion = trim((string) $request->input('direccion', ''));
        $ciudad    = trim((string) $request->input('ciudad', '')) ?: 'Bello';
        if ($direccion === '') {
            return response()->json(['ok' => false, 'msg' => 'Escribe la dirección.'], 422);
        }

        app(\App\Services\TenantManager::class)->set($tenant);

        try {
            // 🎯 Si vienen coordenadas exactas (de Google Places) → validamos por
            //    punto directo (sin re-geocodificar, sin ambigüedad de municipio).
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            if (is_numeric($lat) && is_numeric($lng)) {
                $res = app(\App\Services\SedeResolverService::class)
                    ->resolverParaPunto((float) $lat, (float) $lng, $tenant->id);
                $cub  = (bool) ($res['cubierta'] ?? false);
                $sede = $res['sede'] ?? null;
                return response()->json([
                    'ok'              => true,
                    'cubierta'        => $cub,
                    'costo_envio'     => ($cub && $sede) ? (float) ($sede->cobertura_costo_envio ?? 0) : null,
                    'tiempo_estimado' => ($cub && $sede) ? ($sede->cobertura_tiempo_min ?? null) : null,
                    'pedido_minimo'   => ($cub && $sede) ? (float) ($sede->cobertura_pedido_minimo ?? 0) : null,
                    'sede'            => $sede->nombre ?? null,
                    'mensaje'         => $cub ? null : 'Esa dirección queda fuera de cobertura. Puedes recoger en sede.',
                ]);
            }

            // Fallback: validar por texto de dirección (geocode + polígonos).
            $sedeId = \App\Models\Sede::where('tenant_id', $tenant->id)
                ->where('activa', true)->value('id');

            $ctrl = app(\App\Http\Controllers\WhatsappWebhookController::class);
            $m = new \ReflectionMethod($ctrl, 'validarCoberturaDireccion');
            $m->setAccessible(true);
            $res = $m->invoke($ctrl, $direccion, '', $ciudad, $sedeId, null);

            return response()->json([
                'ok'              => true,
                'cubierta'        => (bool) ($res['cubierta'] ?? false),
                'costo_envio'     => $res['costo_envio'] ?? null,
                'tiempo_estimado' => $res['tiempo_estimado'] ?? null,
                'pedido_minimo'   => $res['pedido_minimo'] ?? null,
                'sede'            => $res['sede_sugerida'] ?? null,
                'mensaje'         => $res['mensaje_para_cliente'] ?? ($res['mensaje_sugerido'] ?? null),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => 'No se pudo validar la cobertura.'], 200);
        }
    }

    /**
     * 🛒 Crea el pedido en KIVOX desde el checkout de la carta y notifica al
     * cliente por WhatsApp (plantilla pedido_confirmado). Reusa exactamente la
     * misma lógica del pedido manual del panel (guardarPedidoDesdeToolCall).
     * Re-precia los productos del lado servidor (no confía en el navegador).
     */
    public function crearPedido(Request $request, string $slug)
    {
        $tenant = $this->resolverTenant($slug);
        app(\App\Services\TenantManager::class)->set($tenant);

        $cedula    = preg_replace('/\D+/', '', (string) $request->input('cedula', ''));
        $nombre    = trim((string) $request->input('nombre', ''));
        $cel       = preg_replace('/\D+/', '', (string) $request->input('celular', ''));
        $direccion = trim((string) $request->input('direccion', ''));
        $lat       = $request->input('lat');
        $lng       = $request->input('lng');
        $costoEnvio = (float) $request->input('costo_envio', 0);
        $items     = $request->input('items', []);
        $metodo    = $request->input('metodo_entrega', 'domicilio') === 'recoger' ? 'recoger' : 'domicilio';
        $sedeSel   = (int) $request->input('sede_id', 0);

        if (strlen($cedula) < 5)   return response()->json(['ok' => false, 'msg' => 'Falta tu cédula.'], 422);
        if ($nombre === '')        return response()->json(['ok' => false, 'msg' => 'Falta el nombre.'], 422);
        if (strlen($cel) < 7)      return response()->json(['ok' => false, 'msg' => 'Falta el celular.'], 422);
        if (empty($items) || !is_array($items)) return response()->json(['ok' => false, 'msg' => 'El carrito está vacío.'], 422);
        if ($metodo === 'domicilio' && $direccion === '') return response()->json(['ok' => false, 'msg' => 'Falta la dirección.'], 422);
        if ($metodo === 'recoger') {
            $sedeOk = \App\Models\Sede::where('tenant_id', $tenant->id)->where('activa', true)->where('id', $sedeSel)->exists();
            if (!$sedeOk) return response()->json(['ok' => false, 'msg' => 'Elige la sede donde vas a recoger.'], 422);
        }

        // Teléfono internacional (Colombia): 10 dígitos que empiezan por 3 → +57.
        $tel = $cel;
        if (strlen($tel) === 10 && $tel[0] === '3') $tel = '57' . $tel;

        // 🔒 Re-precio en el servidor (no confiar en el precio del navegador).
        $products = [];
        foreach ($items as $it) {
            $pid = (int) ($it['id'] ?? 0);
            $qty = (float) ($it['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $p = DB::table('productos')->where('tenant_id', $tenant->id)
                ->where('id', $pid)->where('activo', true)->first();
            if (!$p) continue;
            $products[] = [
                'name'            => $p->nombre,
                'quantity'        => $qty,
                'unit'            => $p->unidad ?: 'und',
                'precio_unitario' => (float) $p->precio_base,
                'observacion'     => '',
            ];
        }
        if (empty($products)) return response()->json(['ok' => false, 'msg' => 'Los productos no son válidos.'], 422);

        $orderData = [
            'products'       => $products,
            'customer_name'  => $nombre,
            'cedula'         => $cedula,
            'phone'          => $tel,
            'payment_method' => 'efectivo',
            'notes'          => '[PEDIDO DESDE CARTA WEB]',
            'manual'         => true,
        ];

        if ($metodo === 'recoger') {
            // 🏪 Recoger en sede: sin dirección, sin costo de envío.
            $sede = \App\Models\Sede::where('tenant_id', $tenant->id)->find($sedeSel);
            $orderData['metodo_entrega'] = 'recoger';
            $orderData['pickup']         = true;
            $orderData['address']        = '';
            $orderData['location']       = $sede->nombre ?? '';
            $orderData['sede_id']        = $sedeSel;
        } else {
            // 🛵 Domicilio: SIEMPRE se despacha desde Hacienda Selva (su consecutivo
            //    HGI). Fallback a la primera sede activa si no existe "Selva".
            $sedeId = \App\Models\Sede::where('tenant_id', $tenant->id)->where('activa', true)
                        ->where('nombre', 'like', '%SELVA%')->value('id')
                    ?: \App\Models\Sede::where('tenant_id', $tenant->id)->where('activa', true)->value('id');
            $orderData['metodo_entrega']     = 'domicilio';
            $orderData['address']            = $direccion;
            $orderData['location']           = '';
            $orderData['shipping_cost']      = $costoEnvio;
            $orderData['costo_envio']        = $costoEnvio;
            $orderData['costo_envio_manual'] = true;
            if ($sedeId) $orderData['sede_id'] = (int) $sedeId;
            if (is_numeric($lat) && is_numeric($lng)) {
                $orderData['location_lat'] = (float) $lat;
                $orderData['location_lng'] = (float) $lng;
            }
        }

        try {
            $conv = \App\Models\ConversacionWhatsapp::firstOrCreate(
                ['telefono_normalizado' => $tel],
                ['tenant_id' => $tenant->id, 'estado' => 'activa', 'canal' => 'carta_web']
            );
            $convService = app(\App\Services\ConversacionService::class);
            $controller  = app(\App\Http\Controllers\WhatsappWebhookController::class);

            $resultado = $controller->guardarPedidoDesdeToolCall(
                $orderData, $tel, $nombre, [],
                'carta_' . substr(md5($tel . uniqid('', true)), 0, 8),
                $conv->connection_id ? (string) $conv->connection_id : null,
                $conv, $convService
            );

            $pedido = \App\Models\Pedido::where('telefono', $tel)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->orderByDesc('id')->first();

            if (!$pedido) {
                $motivo = trim(strip_tags((string) $resultado)) ?: 'No se pudo crear el pedido.';
                return response()->json(['ok' => false, 'msg' => mb_substr($motivo, 0, 200)], 200);
            }

            try { app(\App\Services\EstadoPedidoService::class)->marcarConfirmado($conv, $pedido->id); }
            catch (\Throwable $e) { /* no bloquear */ }

            // Número visible del pedido (numero_pedido), NO el id interno.
            $numPedido = $pedido->numero_pedido ?: $pedido->id;

            // 📲 Notificar al cliente por WhatsApp (plantilla pedido_confirmado).
            $notificado = false;
            try {
                $primerNombre = explode(' ', trim($nombre))[0] ?: 'Cliente';
                $notificado = (bool) app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarPlantilla($tel, 'pedido_confirmado', [$primerNombre, (string) $numPedido],
                        $tenant->id, 'es', null, null, true);
            } catch (\Throwable $e) {
                \Log::warning('Carta: notificación al cliente falló: ' . $e->getMessage());
            }

            return response()->json([
                'ok'         => true,
                'pedido_id'  => $numPedido,
                'total'      => (float) $pedido->total,
                'notificado' => $notificado,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Carta crearPedido falló: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'No se pudo crear el pedido. Intenta de nuevo.'], 200);
        }
    }

    private function resolverTenant(string $slug): Tenant
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('slug', $slug)->where('activo', true)->first();
        abort_unless($tenant, 404, 'Catálogo no disponible.');
        return $tenant;
    }
}
