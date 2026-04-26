<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

use App\Models\Pedido;
use App\Http\Controllers\WhatsappWebhookController;
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

Route::post('/whatsapp-webhook', [WhatsappWebhookController::class, 'receive']);

// Wompi: receptor de eventos de pagos. Validación de firma adentro del controller.
Route::post('/wompi/webhook', [\App\Http\Controllers\WompiWebhookController::class, 'recibir'])
    ->name('wompi.webhook');

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