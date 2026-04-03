<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Events\MessageSent;
use App\Events\PedidoConfirmado;
use App\Http\Controllers\PedidoController;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\DetallePedido;

Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');





Route::get('/test-broadcast', function() {
    // Crear un pedido de prueba
    $sede = Sede::first();
    
    $pedido = Pedido::create([
        'sede_id' => $sede?->id ?? 1,
        'fecha_pedido' => now(),
        'hora_entrega' => '18:00:00',
        'estado' => 'confirmado',
        'total' => 50000,
        'cliente_nombre' => 'TEST - Juan Pérez',
        'telefono' => '573001234567',
        'canal' => 'whatsapp',
        'notas' => 'Pedido de prueba para testing',
    ]);
    
    // Crear detalles
    DetallePedido::create([
        'pedido_id' => $pedido->id,
        'producto' => 'Lomo de res',
        'cantidad' => 2.000,
        'unidad' => 'kg',
        'precio_unitario' => 15000,
        'subtotal' => 30000,
    ]);
    
    DetallePedido::create([
        'pedido_id' => $pedido->id,
        'producto' => 'Pechuga de pollo',
        'cantidad' => 1.000,
        'unidad' => 'kg',
        'precio_unitario' => 20000,
        'subtotal' => 20000,
    ]);

    // Disparar el evento
    broadcast(new PedidoConfirmado($pedido));

    return response()->json([
        'message' => '✅ Evento disparado correctamente',
        'pedido_id' => $pedido->id,
        'link_panel' => url('/pedidos')
    ]);
});