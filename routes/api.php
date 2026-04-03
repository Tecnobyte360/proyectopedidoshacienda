<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Models\Pedido;
use App\Http\Controllers\WhatsappWebhookController;

// =========================================================
// 🌐 RUTAS BÁSICAS DEL WEBHOOK Y API
// =========================================================

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Verificación del webhook
Route::get('/whatsapp-webhook', function () {
    return response()->json([
        'status' => 'webhook active'
    ]);
});

// =========================================================
// 📩 WEBHOOK PRINCIPAL
// =========================================================

Route::post('/whatsapp-webhook', [WhatsappWebhookController::class, 'receive']);

// =========================================================
// 🛠️ RUTAS DE ADMINISTRACIÓN Y DEBUGGING
// =========================================================

// Resetear conversación
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

// Ver historial de conversación
Route::get('/whatsapp-webhook/history/{phone}', function ($phone) {
    $cacheKey = "whatsapp_chat_{$phone}";
    $history  = Cache::get($cacheKey, []);

    return response()->json([
        'phone'          => $phone,
        'messages_count' => count($history),
        'history'        => $history,
    ]);
});

// Consultar pedidos de un cliente
Route::get('/whatsapp-webhook/orders/{phone}', function ($phone) {

    $formatearCantidad = function (float $cantidad): string {
        if (fmod($cantidad, 1.0) == 0.0) {
            return number_format($cantidad, 0, ',', '.');
        }

        return number_format($cantidad, 2, ',', '.');
    };

    $pedidos = Pedido::where('telefono', $phone)
        ->with(['sede', 'detalles'])
        ->orderBy('fecha_pedido', 'desc')
        ->get();

    if ($pedidos->isEmpty()) {
        return response()->json([
            'status'  => 'not_found',
            'message' => 'No se encontraron solicitudes',
            'phone'   => $phone,
        ], 404);
    }

    return response()->json([
        'status'       => 'success',
        'phone'        => $phone,
        'total_orders' => $pedidos->count(),
        'orders'       => $pedidos->map(function ($pedido) use ($formatearCantidad) {
            return [
                'id'           => $pedido->id,
                'fecha'        => $pedido->fecha_pedido->format('d/m/Y H:i'),
                'estado'       => $pedido->estado,
                'hora_entrega' => $pedido->hora_entrega,
                'sede'         => $pedido->sede->nombre ?? 'No especificada',
                'cliente'      => $pedido->cliente_nombre,
                'total'        => $pedido->total,
                'productos'    => $pedido->detalles->map(function ($detalle) use ($formatearCantidad) {
                    return [
                        'producto' => $detalle->producto,
                        'cantidad' => $formatearCantidad($detalle->cantidad),
                        'unidad'   => $detalle->unidad,
                    ];
                }),
            ];
        }),
    ]);
});

// Actualizar estado de un pedido
Route::patch('/whatsapp-webhook/orders/{id}/status', function (Request $request, $id) {
    $request->validate([
        'estado' => 'required|in:confirmado,en_preparacion,listo,entregado,cancelado',
    ]);

    $pedido = Pedido::findOrFail($id);
    $estadoAnterior = $pedido->estado;

    $pedido->estado = $request->estado;
    $pedido->save();

    $mensajeEstado = match ($request->estado) {
        'en_preparacion' => "🔵 Tu solicitud #{$id} está en gestión. Te vamos contando cualquier novedad.",
        'listo'          => "🟢 Tu solicitud #{$id} quedó confirmada/lista. Gracias por escribir a Doblamos.",
        'entregado'      => "✅ La solicitud #{$id} ya quedó finalizada. Gracias por contar con Doblamos.",
        'cancelado'      => "🔴 La solicitud #{$id} fue cancelada. Si no fuiste tú, por favor escríbenos.",
        default          => "Tu solicitud #{$id} actualizó su estado.",
    };

    try {
        Http::withToken(env('WHATSAPP_BOT_TOKEN'))
            ->timeout(10)
            ->post('http://localhost:4002/api/send', [
                'phoneNumber' => $pedido->telefono,
                'message'     => $mensajeEstado,
            ]);
    } catch (\Throwable $e) {
        Log::error('Error notificando cambio de estado', [
            'pedido_id' => $id,
            'error'     => $e->getMessage(),
        ]);
    }

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