<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Events\PedidoConfirmado;
use App\Livewire\Pedidos\Index as PedidosIndex;
use App\Livewire\Pedidos\SeguimientoPedido;
use App\Livewire\Productos\Index as ProductosIndex;
use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Livewire\Importaciones\Index as ImportacionesIndex;
use App\Livewire\Cortes\Index as CortesIndex;
use App\Livewire\Campanas\Index as CampanasIndex;
use App\Livewire\Integraciones\Index as IntegracionesIndex;
use App\Livewire\UsuariosInternos\Index as UsuariosInternosIndex;
use App\Livewire\Departamentos\Index as DepartamentosIndex;
use App\Livewire\ChatWidgets\Index as ChatWidgetsIndex;
use App\Http\Controllers\ChatWidgetController;
use App\Livewire\Promociones\Index as PromocionesIndex;
use App\Livewire\Domiciliarios as DomiciliariosIndex;
use App\Livewire\Reportes\Index as ReportesIndex;
use App\Livewire\Zonas\Index as ZonasIndex;
use App\Livewire\Despachos\Index as DespachosIndex;
use App\Livewire\Ans\Index as AnsIndex;
use App\Livewire\Configuracion\Bot as ConfiguracionBot;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Conversaciones\Index as ConversacionesIndex;
use App\Livewire\Chat\Index as ChatIndex;
use App\Livewire\Alertas\Index as AlertasIndex;
use App\Livewire\Felicitaciones\Index as FelicitacionesIndex;
use App\Livewire\Encuestas\Index as EncuestasIndex;
use App\Livewire\Sedes\Index as SedesIndex;
use App\Livewire\Usuarios\Index as UsuariosIndex;
use App\Livewire\Roles\Index as RolesIndex;
use App\Livewire\Admin\Tenants\Index as AdminTenantsIndex;
use App\Livewire\Admin\Planes\Index as AdminPlanesIndex;
use App\Livewire\Admin\Suscripciones\Index as AdminSuscripcionesIndex;
use App\Livewire\Admin\Pagos\Index as AdminPagosIndex;
use App\Livewire\Admin\Documentacion as AdminDocumentacion;
use App\Livewire\Admin\ConfiguracionPlataforma as AdminConfiguracionPlataforma;
use App\Http\Controllers\AuthController;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\DetallePedido;

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS — protegidas con permisos
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

Route::get('/', function () {
    $u = auth()->user();
    // Super-admin sin impersonar va al panel de tenants
    if ($u && $u->tenant_id === null && $u->hasRole('super-admin') && !session()->has('tenant_imitado_id')) {
        return redirect()->route('admin.tenants.index');
    }
    return redirect('/pedidos');
});

// 🏢 Rutas operativas — bloqueadas para super-admin sin impersonar
Route::middleware(['no_super_sin_imp'])->group(function () {
    Route::get('/pedidos', PedidosIndex::class)->middleware('permission:pedidos.ver')->name('pedidos.index');

    Route::get('/productos',     ProductosIndex::class)->middleware('permission:productos.ver')->name('productos.index');
    Route::get('/categorias',    CategoriasIndex::class)->middleware('permission:categorias.gestionar')->name('categorias.index');
    Route::get('/cortes',        CortesIndex::class)->middleware('permission:productos.ver')->name('cortes.index');
    Route::get('/campanas',      CampanasIndex::class)->middleware('permission:conversaciones.ver')->name('campanas.index');
    Route::get('/importaciones', ImportacionesIndex::class)->middleware('permission:productos.ver')->name('importaciones.index');
    Route::get('/integraciones', IntegracionesIndex::class)->middleware(['permission:productos.ver', 'role:super-admin'])->name('integraciones.index');
    Route::get('/usuarios-internos', UsuariosInternosIndex::class)->middleware('permission:conversaciones.ver')->name('usuarios-internos.index');
    Route::get('/departamentos',     DepartamentosIndex::class)->middleware('permission:conversaciones.ver')->name('departamentos.index');
    Route::get('/chat-widgets',      ChatWidgetsIndex::class)->middleware(['permission:conversaciones.ver', 'role:super-admin'])->name('chat-widgets.index');
    Route::get('/importaciones/plantilla/{tipo}', function (string $tipo) {
        $tipo = in_array($tipo, ['productos', 'categorias'], true) ? $tipo : 'productos';

        $headers = $tipo === 'categorias'
            ? ['nombre', 'descripcion', 'icono_emoji', 'color', 'orden', 'activo']
            : ['codigo', 'nombre', 'categoria', 'unidad', 'precio_base', 'descripcion_corta', 'descripcion', 'palabras_clave', 'activo', 'destacado', 'orden'];

        $ejemplo = $tipo === 'categorias'
            ? ['Carnes', 'Cortes frescos de res, cerdo y pollo', '🥩', '#d68643', 1, 'si']
            : ['P001', 'Pechuga de pollo', 'Carnes', 'lb', 15000, 'Pechuga fresca', 'Pechuga de pollo fresca, sin piel', 'pollo,pechuga,blanca', 'si', 'no', 1];

        $content = implode(',', $headers) . "\n" . implode(',', $ejemplo) . "\n";

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=plantilla_{$tipo}.csv",
        ]);
    })->middleware('permission:productos.ver')->name('importaciones.plantilla');
    Route::get('/promociones',   PromocionesIndex::class)->middleware('permission:promociones.gestionar')->name('promociones.index');
    Route::get('/domiciliarios', DomiciliariosIndex::class)->middleware('permission:domiciliarios.gestionar')->name('domiciliarios.index');
    Route::get('/zonas',         ZonasIndex::class)->middleware('permission:zonas.gestionar')->name('zonas.index');
    Route::get('/despachos',     DespachosIndex::class)->middleware('permission:despachos.gestionar')->name('despachos.index');
    Route::get('/reportes',      ReportesIndex::class)->middleware('permission:reportes.ver')->name('reportes.index');
    Route::get('/ans-tiempos',   AnsIndex::class)->middleware('permission:ans.gestionar')->name('ans.index');
    Route::get('/configuracion/bot', ConfiguracionBot::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('configuracion.bot');
    Route::get('/clientes',          ClientesIndex::class)->middleware('permission:clientes.ver')->name('clientes.index');
    Route::get('/conversaciones',    ConversacionesIndex::class)->middleware('permission:conversaciones.ver')->name('conversaciones.index');
    Route::get('/chat',              ChatIndex::class)->middleware('permission:chat.usar')->name('chat.index');
    // Proxy autenticado para servir las medias de los estados de WhatsApp
    Route::get('/whatsapp-status/media/{filename}', \App\Http\Controllers\WhatsappStatusMediaController::class)
        ->where('filename', '[\w\-\.]+')
        ->middleware('permission:chat.usar')
        ->name('whatsapp-status.media');
    Route::get('/alertas',           AlertasIndex::class)->middleware(['permission:alertas.ver', 'role:super-admin'])->name('alertas.index');
    Route::get('/felicitaciones',    FelicitacionesIndex::class)->middleware('permission:felicitaciones.ver')->name('felicitaciones.index');
    Route::get('/encuestas',         EncuestasIndex::class)->middleware('permission:reportes.ver')->name('encuestas.index');
    Route::get('/sedes',             SedesIndex::class)->middleware('permission:sedes.gestionar')->name('sedes.index');
    Route::get('/usuarios',          UsuariosIndex::class)->middleware('permission:usuarios.ver')->name('usuarios.index');
    // Roles ya NO se gestionan por tenant — los roles son globales
    // (compartidos por todos), entonces solo el super-admin desde el dominio
    // principal puede tocarlos. Lo movemos abajo con `solo_principal`.
});

// 🔒 Roles globales — solo super-admin desde dominio principal
Route::get('/roles', RolesIndex::class)
    ->middleware(['permission:roles.gestionar', 'solo_principal'])
    ->name('roles.index');

// 🌟 SUPER-ADMIN — solo TecnoByte360 (dueño plataforma).
// Doble blindaje: permiso + middleware "solo_principal" (404 si entran desde subdominio).
Route::middleware(['solo_principal'])->group(function () {
    Route::get('/admin/tenants',       AdminTenantsIndex::class)->middleware('permission:tenants.gestionar')->name('admin.tenants.index');
    Route::get('/admin/planes',        AdminPlanesIndex::class)->middleware('permission:planes.gestionar')->name('admin.planes.index');
    Route::get('/admin/suscripciones', AdminSuscripcionesIndex::class)->middleware('permission:suscripciones.gestionar')->name('admin.suscripciones.index');
    Route::get('/admin/pagos',         AdminPagosIndex::class)->middleware('permission:pagos.gestionar')->name('admin.pagos.index');
    Route::get('/admin/documentacion', AdminDocumentacion::class)->middleware('permission:tenants.gestionar')->name('admin.documentacion');
    Route::get('/admin/configuracion-plataforma', AdminConfiguracionPlataforma::class)->middleware('permission:tenants.gestionar')->name('admin.configuracion-plataforma');

    // 🎭 Salir del modo impersonación (vuelve al super-admin)
    // Soporta GET y POST por compatibilidad — pero POST es lo recomendado
    // (no cacheable, no interceptado por Livewire, semánticamente correcto).
    Route::match(['get', 'post'], '/admin/dejar-impersonar', function () {
        session()->forget('tenant_imitado_id');
        session()->save();
        return redirect()->route('admin.tenants.index')
            ->with('info', '✓ Volviste al modo super-admin.')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
            ]);
    })->middleware('permission:tenants.gestionar')->name('admin.dejar-impersonar');
});

}); // fin auth group

Route::get('/seguimiento-pedido/{codigo}', SeguimientoPedido::class)
    ->name('pedidos.seguimiento');

// Encuesta pública post-entrega (sin auth — el cliente la abre desde su WhatsApp)
Route::get('/encuesta/{token}', \App\Livewire\Encuestas\Responder::class)
    ->where('token', '[\w\-]+')
    ->name('encuesta.responder');

Route::get('/test-broadcast', function () {

    $sede = Sede::first();

    // 🔥 CREAR PEDIDO CON CÓDIGO DE SEGUIMIENTO
    $pedido = Pedido::create([
        'sede_id'        => $sede?->id ?? 1,
        'fecha_pedido'   => now(),
        'hora_entrega'   => '18:00:00',

        // ⚠️ USA EL NUEVO ESTADO
        'estado'         => 'nuevo',

        'total'          => 50000,
        'cliente_nombre' => 'TEST - Juan Pérez',
        'telefono'       => '573216499744',
        'canal'          => 'whatsapp',
        'notas'          => 'Pedido de prueba para testing',

        // 🔥 CLAVE PARA EL TRACKING
        'codigo_seguimiento' => Str::uuid(),
    ]);

    // 🔥 DETALLES
    DetallePedido::create([
        'pedido_id'       => $pedido->id,
        'producto'        => 'Lomo de res',
        'cantidad'        => 2.000,
        'unidad'          => 'kg',
        'precio_unitario' => 15000,
        'subtotal'        => 30000,
    ]);

    DetallePedido::create([
        'pedido_id'       => $pedido->id,
        'producto'        => 'Pechuga de pollo',
        'cantidad'        => 1.000,
        'unidad'          => 'kg',
        'precio_unitario' => 20000,
        'subtotal'        => 20000,
    ]);

    // 🔥 DISPARAR EVENTO (tiempo real)
    broadcast(new PedidoConfirmado($pedido));

    // 🔥 LINK DE SEGUIMIENTO
    $linkSeguimiento = route('pedidos.seguimiento', $pedido->codigo_seguimiento);

    return response()->json([
        'message'           => '✅ Pedido creado correctamente',
        'pedido_id'         => $pedido->id,
        'link_panel'        => url('/pedidos'),
        'link_seguimiento'  => $linkSeguimiento, // 🔥 ESTE ES EL IMPORTANTE
    ]);
});


// ═══════════════════════════════════════════════════════════════════════════
// WIDGET DE CHAT WEB — rutas PÚBLICAS (sin auth) para embeber en sitios externos
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/widget.js',                        [ChatWidgetController::class, 'script'])->withoutMiddleware(['web']);
Route::get('/widget-preview',                   fn() => view('widget.preview', ['token' => request('token')]))->withoutMiddleware(['web']);
Route::get('/api/widget/{token}/config',        [ChatWidgetController::class, 'config'])->withoutMiddleware(['web']);
Route::post('/api/widget/{token}/mensaje',      [ChatWidgetController::class, 'mensaje'])->withoutMiddleware(['web']);
Route::get('/api/widget/{token}/mensajes',      [ChatWidgetController::class, 'mensajes'])->withoutMiddleware(['web']);
Route::options('/api/widget/{token}/{any?}',    [ChatWidgetController::class, 'preflight'])->where('any', '.*')->withoutMiddleware(['web']);
