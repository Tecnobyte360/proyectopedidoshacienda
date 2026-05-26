<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

use App\Models\Pedido;
use App\Http\Controllers\WhatsappWebhookController;
use App\Http\Controllers\MetaWhatsappWebhookController;
use App\Http\Controllers\Api\V1\CategoriaApiController;
use App\Http\Controllers\Api\V1\ProductoApiController;
use App\Http\Controllers\Api\V1\PromocionApiController;
use App\Http\Controllers\Api\V1\ZonaApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/whatsapp-webhook', function () {
    return response()->json([
        'status' => 'webhook active'
    ]);
});

// 🛡️ BUG-C4: Middleware whatsapp.webhook protege contra:
// - Rate limit (siempre activo, 120 req/min por IP)
// - IP whitelist (opcional, via config services.whatsapp.allowed_ips)
// - Token compartido (opcional, via WHATSAPP_WEBHOOK_TOKEN en .env)
Route::post('/whatsapp-webhook', [WhatsappWebhookController::class, 'receive'])
    ->middleware('whatsapp.webhook');

// 📱 Meta WhatsApp Cloud API — webhook único multi-tenant.
// Identifica el tenant por phone_number_id del payload.
Route::get('/meta/whatsapp/webhook',  [MetaWhatsappWebhookController::class, 'verify']);
Route::post('/meta/whatsapp/webhook', [MetaWhatsappWebhookController::class, 'receive']);

// 📷 Meta Instagram Direct Messages — webhook único multi-tenant.
// Identifica el tenant por page_id del payload.
Route::get('/meta/instagram/webhook',  [\App\Http\Controllers\InstagramWebhookController::class, 'verify']);
Route::post('/meta/instagram/webhook', [\App\Http\Controllers\InstagramWebhookController::class, 'receive']);

// Webhook ESPECÍFICO POR TENANT — identifica al tenant por slug en la URL.
// Recomendado en producción: cada tenant tiene su URL única que copia y
// pega en TecnoByteApp para SU conexión de WhatsApp.
Route::post('/whatsapp-webhook/tenant/{slug}', [WhatsappWebhookController::class, 'receivePorTenant'])
    ->middleware('whatsapp.webhook');
Route::get('/whatsapp-webhook/tenant/{slug}', function (string $slug) {
    return response()->json([
        'ok'          => true,
        'tenant_slug' => $slug,
        'estado'      => 'webhook activo, esperando POST',
    ]);
});

// Wompi: receptor de eventos de pagos POR TENANT (slug en la URL).
// Cada tenant configura en su panel de Wompi:
//   https://{APP_URL}/api/wompi/webhook/{slug}
// Eso permite identificar el tenant SIN depender de la reference, validar la
// firma con su events_secret y aislar errores entre comercios.
Route::post('/wompi/webhook/{slug}', [\App\Http\Controllers\WompiWebhookController::class, 'recibir'])
    ->where('slug', '[a-z0-9-]+')
    ->name('wompi.webhook');

// Compatibilidad: ruta legacy sin slug (por si algún tenant ya la configuró asi).
// El controller resuelve el tenant via wompi_reference del pedido.
Route::post('/wompi/webhook', [\App\Http\Controllers\WompiWebhookController::class, 'recibir'])
    ->name('wompi.webhook.legacy');

// 💳 SaaS Billing — webhook de Wompi del DUEÑO de Kivox (TecnoByte360).
// Cobro de mensualidades a los tenants. Diferente de los webhooks tenant→cliente arriba.
Route::post('/saas-billing/wompi/webhook', \App\Http\Controllers\SaasBillingWompiWebhookController::class)
    ->name('saas-billing.wompi.webhook');

// Endpoints de intervención humana (chat en vivo del admin)
Route::post('/chat/enviar-manual',   [WhatsappWebhookController::class, 'enviarMensajeManual']);
Route::post('/chat/tomar-control',   [WhatsappWebhookController::class, 'tomarControl']);
Route::post('/chat/devolver-al-bot', [WhatsappWebhookController::class, 'devolverAlBot']);
Route::get('/whatsapp-webhook/orders/search', [WhatsappWebhookController::class, 'searchOrders']);
Route::get('/whatsapp-webhook/orders/{id}', [WhatsappWebhookController::class, 'showOrder']);

Route::delete('/whatsapp-webhook/reset/{phone}', function ($phone) {
    $cacheKey = "whatsapp_chat_{$phone}";

    if (Cache::has($cacheKey)) {
        Cache::forget($cacheKey);

        return response()->json([
            'status'  => 'success',
            'message' => 'Conversación eliminada',
            'phone'   => $phone,
        ]);
    }

    return response()->json([
        'status'  => 'not_found',
        'message' => 'No hay conversación activa',
        'phone'   => $phone,
    ], 404);
});

Route::get('/whatsapp-webhook/history/{phone}', function ($phone) {
    $cacheKey = "whatsapp_chat_{$phone}";
    $history  = Cache::get($cacheKey, []);

    return response()->json([
        'phone'          => $phone,
        'messages_count' => count($history),
        'history'        => $history,
    ]);
});

/*
|--------------------------------------------------------------------------
| API REST v1 — Catálogo (autenticada con X-API-KEY)
|--------------------------------------------------------------------------
| Lectura pública (GET) y escritura protegida con header X-API-KEY.
| Configura API_KEY en .env.
*/
Route::prefix('v1')->group(function () {

    // Lectura pública
    Route::get('categorias',          [CategoriaApiController::class, 'index']);
    Route::get('categorias/{id}',     [CategoriaApiController::class, 'show']);

    Route::get('productos',           [ProductoApiController::class, 'index']);
    Route::get('productos/{id}',      [ProductoApiController::class, 'show']);

    Route::get('promociones',         [PromocionApiController::class, 'index']);
    Route::get('promociones/{id}',    [PromocionApiController::class, 'show']);

    // Zonas de cobertura — lectura pública + endpoint de resolución
    Route::get ('zonas',              [ZonaApiController::class, 'index']);
    Route::post('zonas/resolver',     [ZonaApiController::class, 'resolver']);

    // Escritura protegida
    Route::middleware('api.key')->group(function () {
        Route::post  ('categorias',        [CategoriaApiController::class, 'store']);
        Route::match (['put','patch'], 'categorias/{id}', [CategoriaApiController::class, 'update']);
        Route::delete('categorias/{id}',   [CategoriaApiController::class, 'destroy']);

        Route::post  ('productos',         [ProductoApiController::class, 'store']);
        Route::match (['put','patch'], 'productos/{id}',  [ProductoApiController::class, 'update']);
        Route::delete('productos/{id}',    [ProductoApiController::class, 'destroy']);

        Route::post  ('promociones',       [PromocionApiController::class, 'store']);
        Route::match (['put','patch'], 'promociones/{id}',[PromocionApiController::class, 'update']);
        Route::delete('promociones/{id}',  [PromocionApiController::class, 'destroy']);
    });
});

Route::patch('/whatsapp-webhook/orders/{id}/status', function (Request $request, $id) {
    $request->validate([
        'estado' => 'required|in:confirmado,en_preparacion,listo,entregado,cancelado',
    ]);

    $pedido = Pedido::findOrFail($id);
    $estadoAnterior = $pedido->estado;

    $pedido->estado = $request->estado;
    $pedido->save();

    return response()->json([
        'status'  => 'success',
        'message' => 'Estado actualizado',
        'pedido'  => [
            'id'              => $pedido->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $pedido->estado,
        ],
    ]);
});