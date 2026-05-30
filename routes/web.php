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
use App\Livewire\EstadosWhatsapp\Index as EstadosWhatsappIndex;
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

    // 🔑 Recuperación de contraseña
    Route::get('/forgot-password',          [\App\Http\Controllers\PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password',         [\App\Http\Controllers\PasswordResetController::class, 'sendResetLink']);
    Route::get('/reset-password/{token}',   [\App\Http\Controllers\PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password',          [\App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// 🔐 2FA Challenge (entre login y dashboard cuando user tiene 2FA activado)
Route::get('/two-factor-challenge',  [\App\Http\Controllers\TwoFactorController::class, 'showChallenge'])
    ->name('two-factor.challenge');
Route::post('/two-factor-challenge', [\App\Http\Controllers\TwoFactorController::class, 'verifyChallenge'])
    ->name('two-factor.verify');

// 🆕 2FA Enrollment forzado (cuando admin marcó requiere_2fa=true al user)
//    Va ANTES de completar el login: muestra QR + verifica primer código.
Route::get('/two-factor-enroll',  [\App\Http\Controllers\TwoFactorController::class, 'showForcedEnroll'])
    ->name('two-factor.enroll');
Route::post('/two-factor-enroll', [\App\Http\Controllers\TwoFactorController::class, 'confirmForcedEnroll'])
    ->name('two-factor.enroll.confirm');

// 🔐 2FA Settings (gestión usuario logueado)
Route::middleware('auth')->group(function () {
    Route::get('/perfil/seguridad',                \App\Livewire\Auth\SecuritySettings::class)->name('perfil.seguridad');
    Route::post('/perfil/seguridad/iniciar-2fa',   [\App\Http\Controllers\TwoFactorController::class, 'startEnroll'])->name('two-factor.start');
    Route::post('/perfil/seguridad/confirmar-2fa', [\App\Http\Controllers\TwoFactorController::class, 'confirmEnroll'])->name('two-factor.confirm');
    Route::post('/perfil/seguridad/desactivar',    [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS — protegidas con permisos
|--------------------------------------------------------------------------
*/
// 📱 Ruta pública para escanear QR de WhatsApp del tenant actual
// (sin auth porque a veces necesitas verlo en cel sin login)
Route::get('/wa-qr/{tenantId?}', function ($tenantId = null) {
    try {
        $tenant = $tenantId
            ? \App\Models\Tenant::withoutGlobalScopes()->find($tenantId)
            : \App\Models\Tenant::withoutGlobalScopes()->where('slug', 'la-hacienda')->first();

        if (!$tenant) abort(404, 'Tenant no encontrado');

        app(\App\Services\TenantManager::class)->set($tenant);
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $token = $resolver->token();
        $ids = $resolver->connectionIdsDelTenant();
        $connId = $ids[0] ?? null;
        if (!$connId) abort(404, 'Sin conexión configurada');

        $base = rtrim($cred['api_base_url'] ?? '', '/');
        $resp = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->withToken($token)->timeout(10)
            ->get("{$base}/whatsapp/{$connId}");
        $data = $resp->json();

        return view('wa-qr', [
            'tenant'  => $tenant,
            'status'  => $data['status'] ?? '?',
            'qrcode'  => $data['qrcode'] ?? null,
            'phone'   => $data['phoneNumber'] ?? null,
            'battery' => $data['battery'] ?? null,
            'connId'  => $connId,
        ]);
    } catch (\Throwable $e) {
        return response('Error: ' . $e->getMessage(), 500);
    }
});

// 🔄 Endpoint para regenerar QR (DELETE + POST session)
Route::post('/wa-qr/{tenantId}/regenerar', function ($tenantId) {
    try {
        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($tenantId);
        if (!$tenant) abort(404);

        app(\App\Services\TenantManager::class)->set($tenant);
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $token = $resolver->token();
        $ids = $resolver->connectionIdsDelTenant();
        $connId = $ids[0] ?? null;
        if (!$connId) abort(404);

        $base = rtrim($cred['api_base_url'] ?? '', '/');
        \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->timeout(15)->delete("{$base}/whatsappsession/{$connId}");
        sleep(2);
        \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->timeout(15)->post("{$base}/whatsappsession/{$connId}");

        return redirect("/wa-qr/{$tenantId}");
    } catch (\Throwable $e) {
        return response('Error: ' . $e->getMessage(), 500);
    }
});

// 🌐 Ruta '/' PÚBLICA — landing en el root domain (kivox.co), redirect a app en subdominios.
//    Va FUERA del grupo auth para que los visitantes anónimos vean la landing.
Route::get('/', function () {
    $host    = strtolower(request()->getHost());
    $base    = strtolower(config('app.tenant_base_domain', 'tecnobyte360.com'));
    $esRoot  = ($host === $base) || ($host === 'www.' . $base);

    // Root domain (kivox.co / www.kivox.co): landing comercial pública
    if ($esRoot) {
        return view('landing.kivox');
    }

    // Subdominios de tenant (admin/la-hacienda/etc): redirigir según user
    if (!auth()->check()) {
        return redirect('/login');
    }
    $u = auth()->user();
    if ($u->tenant_id === null && $u->hasRole('super-admin') && !session()->has('tenant_imitado_id')) {
        return redirect()->route('admin.tenants.index');
    }
    return redirect('/pedidos');
});

// 📄 Páginas legales públicas (requeridas por App Review de Meta/Instagram)
Route::view('/privacidad',      'legal.privacidad')->name('legal.privacidad');
Route::view('/terminos',        'legal.terminos')->name('legal.terminos');
Route::view('/eliminar-datos',  'legal.eliminar-datos')->name('legal.eliminar-datos');

Route::middleware(['auth'])->group(function () {

// 🏢 Rutas operativas — bloqueadas para super-admin sin impersonar
Route::middleware(['no_super_sin_imp'])->group(function () {
    Route::get('/pedidos', PedidosIndex::class)->middleware('permission:pedidos.ver')->name('pedidos.index');

    Route::get('/productos',     ProductosIndex::class)->middleware('permission:productos.ver')->name('productos.index');
    Route::get('/categorias',    CategoriasIndex::class)->middleware('permission:categorias.gestionar')->name('categorias.index');
    Route::get('/cortes',        CortesIndex::class)->middleware('permission:productos.ver')->name('cortes.index');
    Route::get('/campanas',           CampanasIndex::class)->middleware('permission:campanas.ver|campanas.gestionar')->name('campanas.index');
    Route::get('/campanas/{id}/informe', \App\Livewire\Campanas\Informe::class)
        ->middleware('permission:campanas.ver|campanas.gestionar')
        ->name('campanas.informe');
    Route::get('/estados-whatsapp',  EstadosWhatsappIndex::class)->middleware('permission:campanas.ver|campanas.gestionar')->name('estados-whatsapp.index');
    Route::get('/importaciones', ImportacionesIndex::class)->middleware('permission:productos.ver')->name('importaciones.index');
    Route::get('/integraciones', IntegracionesIndex::class)->middleware(['permission:productos.ver', 'role:super-admin'])->name('integraciones.index');
    Route::get('/integraciones/{integracion}/consultas', \App\Livewire\Integraciones\Consultas::class)
        ->middleware(['permission:productos.ver', 'role:super-admin'])
        ->name('integraciones.consultas');
    Route::get('/integraciones/exports', \App\Livewire\Integraciones\ExportLogs::class)
        ->middleware(['permission:productos.ver', 'role:super-admin'])
        ->name('integraciones.exports');
    Route::get('/integraciones/clientes-erp', \App\Livewire\Integraciones\ClientesErp::class)
        ->middleware(['permission:productos.ver', 'role:super-admin'])
        ->name('integraciones.clientes-erp');
    Route::get('/usuarios-internos', UsuariosInternosIndex::class)->middleware('permission:usuarios_internos.ver|usuarios_internos.gestionar')->name('usuarios-internos.index');
    Route::get('/departamentos',     DepartamentosIndex::class)->middleware('permission:departamentos.gestionar')->name('departamentos.index');
    Route::get('/chat-widgets',      ChatWidgetsIndex::class)->middleware('permission:chat_widgets.gestionar')->name('chat-widgets.index');
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
    Route::get('/zonas/{zona}/editor-mapa', \App\Livewire\Zonas\EditorMapa::class)
        ->middleware('permission:zonas.gestionar')
        ->name('zonas.editor-mapa');
    Route::get('/despachos',     DespachosIndex::class)->middleware('permission:despachos.gestionar|despachos.ver_propios')->name('despachos.index');
    Route::get('/reportes',      ReportesIndex::class)->middleware('permission:reportes.ver')->name('reportes.index');
    Route::get('/ans-tiempos',   AnsIndex::class)->middleware('permission:ans.gestionar')->name('ans.index');
    // 🛡️ Plataforma: solo super-admin (Kivox global). Los tenants NO ven Meta config / Bot config / Monitor LLM / Costos Meta.
    Route::get('/meta-whatsapp', \App\Livewire\MetaWhatsapp\Index::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('meta-whatsapp.index');
    Route::get('/configuracion/bot', ConfiguracionBot::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('configuracion.bot');

    Route::get('/configuracion/informes-negocio', \App\Livewire\Configuracion\InformesNegocio::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('configuracion.informes-negocio');

    Route::get('/configuracion/respuestas-rapidas', \App\Livewire\Configuracion\RespuestasRapidas\Index::class)
        ->middleware('permission:respuestas_rapidas.gestionar')
        ->name('configuracion.respuestas-rapidas');

    Route::get('/configuracion/bot-lecciones', \App\Livewire\Configuracion\BotLecciones\Index::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('configuracion.bot-lecciones');

    // /monitoreo/agente redirige al tab Agente dentro de /monitoreo/llm
    Route::get('/monitoreo/agente', fn () => redirect('/monitoreo/llm?tab=agente'))
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('monitoreo.agente');

    Route::get('/monitoreo/llm', \App\Livewire\Monitoreo\Llm::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('monitoreo.llm');

    // /monitoreo/watchdog redirige al tab Watchdog dentro de /monitoreo/llm
    Route::get('/monitoreo/watchdog', fn () => redirect('/monitoreo/llm?tab=watchdog'))
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('monitoreo.watchdog');

    Route::get('/monitoreo/llamadas', \App\Livewire\Monitoreo\Llamadas::class)
        ->middleware('permission:bot.configurar')
        ->name('monitoreo.llamadas');

    Route::get('/monitoreo/costos-meta', \App\Livewire\Monitoreo\CostosMeta::class)
        ->middleware(['permission:bot.configurar', 'role:super-admin'])
        ->name('monitoreo.costos-meta');

    Route::get('/rutas', \App\Livewire\Rutas\Index::class)
        ->middleware('permission:despachos.gestionar')
        ->name('rutas.index');
    Route::get('/pagos', \App\Livewire\Pagos\Index::class)
        ->middleware('permission:pagos_clientes.ver|pagos_clientes.gestionar')
        ->name('pagos.index');
    Route::get('/clientes',          ClientesIndex::class)->middleware('permission:clientes.ver')->name('clientes.index');
    Route::get('/conversaciones',    ConversacionesIndex::class)->middleware('permission:conversaciones.ver')->name('conversaciones.index');
    Route::get('/chat',              ChatIndex::class)->middleware('permission:chat.usar')->name('chat.index');
    Route::get('/chat/estado/{conversacion}', \App\Livewire\Chat\EstadoPedido::class)
        ->middleware('permission:chat.usar')
        ->name('chat.estado-pedido');

    // 📊 Contador global de mensajes no leídos del tenant actual.
    // Lo usa el JS del layout para actualizar título de la pestaña y favicon.
    Route::get('/api/chat/unread-count', function () {
        $tenantId = app(\App\Services\TenantManager::class)->current()?->id;
        if (!$tenantId) return response()->json(['count' => 0]);

        $count = app(\App\Services\TenantManager::class)->withoutTenant(function () use ($tenantId) {
            return \DB::table('conversaciones_whatsapp')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('no_leidos', '>', 0)
                      ->orWhere('marcada_no_leida', true);
                })
                ->count();
        });

        return response()->json(['count' => (int) $count]);
    })->middleware('permission:chat.usar')->name('chat.unread-count');

    // 📡 /bot-monitor redirige al tab 'envivo' dentro de Monitor LLM
    Route::get('/bot-monitor', fn () => redirect('/monitoreo/llm?tab=envivo'))
        ->middleware('permission:chat.usar')
        ->name('bot.monitor');

    // 🛒 Crear pedido manual (admin/operador)
    Route::get('/pedidos/crear', \App\Livewire\Pedidos\CrearManual::class)
        ->middleware('permission:pedidos.ver')
        ->name('pedidos.crear-manual');
    // Proxy autenticado para servir las medias de los estados de WhatsApp
    Route::get('/whatsapp-status/media/{filename}', \App\Http\Controllers\WhatsappStatusMediaController::class)
        ->where('filename', '[\w\-\.]+')
        ->middleware('permission:chat.usar')
        ->name('whatsapp-status.media');
    Route::get('/alertas',           AlertasIndex::class)->middleware(['permission:alertas.ver', 'role:super-admin'])->name('alertas.index');
    Route::get('/felicitaciones',    FelicitacionesIndex::class)->middleware('permission:felicitaciones.ver')->name('felicitaciones.index');
    Route::get('/encuestas',         EncuestasIndex::class)->middleware('permission:reportes.ver')->name('encuestas.index');
    Route::get('/sedes',             SedesIndex::class)->middleware('permission:sedes.gestionar')->name('sedes.index');
    Route::get('/sedes/{sede}/editor-cobertura', \App\Livewire\Sedes\EditorCobertura::class)
        ->middleware('permission:sedes.gestionar')
        ->name('sedes.editor-cobertura');
    Route::get('/usuarios',          UsuariosIndex::class)->middleware('permission:usuarios.ver')->name('usuarios.index');
    // Roles ya NO se gestionan por tenant — los roles son globales
    // (compartidos por todos), entonces solo el super-admin desde el dominio
    // principal puede tocarlos. Lo movemos abajo con `solo_principal`.
});

// 💳 Página pública de Wompi (sin login) — Wompi redirige aquí tras el pago
// del tenant. NO requiere auth porque el tenant no es usuario interno de Kivox.
Route::get('/billing/gracias', [\App\Http\Controllers\BillingPublicController::class, 'gracias'])
    ->name('billing.gracias');

// 🚫 Pantalla de bloqueo por mora (requiere login para que vea su info)
Route::get('/billing/expirado', [\App\Http\Controllers\BillingPublicController::class, 'expirado'])
    ->middleware('auth')
    ->name('billing.expirado');

// Roles: cada admin gestiona los roles propios de su tenant.
// El componente filtra y bloquea edición de roles globales para no super-admin.
Route::get('/roles', RolesIndex::class)
    ->middleware('permission:roles.gestionar')
    ->name('roles.index');

// 🌟 SUPER-ADMIN — solo TecnoByte360 (dueño plataforma).
// Doble blindaje: permiso + middleware "solo_principal" (404 si entran desde subdominio).
Route::middleware(['solo_principal'])->group(function () {
    // Dashboard unificado (ventas + KPIs SaaS) — apunta a DashboardVentas
    Route::get('/admin/dashboard',     \App\Livewire\Admin\DashboardVentas::class)->middleware('permission:tenants.gestionar')->name('admin.dashboard');
    // Mantener /admin/ventas como alias para no romper enlaces
    Route::get('/admin/ventas',        \App\Livewire\Admin\DashboardVentas::class)->middleware('permission:tenants.gestionar')->name('admin.ventas');
    Route::get('/admin/billing-envios',\App\Livewire\Admin\BillingEnvios::class)->middleware('permission:tenants.gestionar')->name('admin.billing-envios');
    Route::get('/admin/tenants',       AdminTenantsIndex::class)->middleware('permission:tenants.gestionar')->name('admin.tenants.index');
    Route::get('/ivr',                 \App\Livewire\Ivr\Monitor::class)->middleware('permission:tenants.gestionar')->name('ivr.monitor');
    Route::get('/admin/tenants/{slug}/conectar-instagram',
        [\App\Http\Controllers\InstagramOAuthController::class, 'iniciar'])
        ->middleware('permission:tenants.gestionar')
        ->name('admin.tenants.conectar-instagram');
    Route::get('/admin/plantillas-bot', \App\Livewire\Admin\PlantillasBot::class)
        ->middleware('permission:tenants.gestionar')
        ->name('admin.plantillas-bot');
    Route::get('/admin/planes',        AdminPlanesIndex::class)->middleware('permission:planes.gestionar')->name('admin.planes.index');
    Route::get('/admin/suscripciones', AdminSuscripcionesIndex::class)->middleware('permission:suscripciones.gestionar')->name('admin.suscripciones.index');
    Route::get('/admin/pagos',         AdminPagosIndex::class)->middleware('permission:pagos.gestionar')->name('admin.pagos.index');
    Route::get('/admin/documentacion', AdminDocumentacion::class)->middleware('permission:tenants.gestionar')->name('admin.documentacion');
    Route::get('/admin/configuracion-plataforma', AdminConfiguracionPlataforma::class)->middleware('permission:tenants.gestionar')->name('admin.configuracion-plataforma');

    // 📥 Importar histórico de WhatsApp desde exports .txt del celular
    Route::get('/admin/importar-historial-whatsapp', \App\Livewire\Admin\ImportarHistorialWa::class)
        ->middleware('permission:chat.usar')
        ->name('admin.importar-historial-wa');

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

    // 🏢 Cambiar tenant que el super-admin está VIENDO en la página actual.
    // Usado por el componente <x-tenant-view-selector> en paginas de plataforma
    // (Bot WhatsApp, Meta WhatsApp, Monitor LLM, etc).
    Route::post('/admin/ver-tenant', function (\Illuminate\Http\Request $r) {
        $tenantId = $r->input('tenant_id');
        $redirect = $r->input('redirect_to') ?: url()->previous();

        if ($tenantId === '' || $tenantId === null) {
            session()->forget('tenant_imitado_id');
        } else {
            session(['tenant_imitado_id' => (int) $tenantId]);
        }
        session()->save();

        return redirect()->to($redirect)
            ->withHeaders(['Cache-Control' => 'no-store']);
    })->middleware('permission:tenants.gestionar')->name('admin.ver-tenant');
});

}); // fin auth group

Route::get('/seguimiento-pedido/{codigo}', SeguimientoPedido::class)
    ->name('pedidos.seguimiento');

// Encuesta pública post-entrega (sin auth — el cliente la abre desde su WhatsApp)
Route::get('/encuesta/{token}', \App\Livewire\Encuestas\Responder::class)
    ->where('token', '[\w\-]+')
    ->name('encuesta.responder');

// Portal del domiciliario (sin auth — accede con su token único desde el celular)
Route::get('/d/{token}', \App\Livewire\Domiciliarios\Portal::class)
    ->where('token', '[\w\-]+')
    ->name('domiciliario.portal');

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
