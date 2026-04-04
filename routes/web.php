<?php

use Illuminate\Support\Facades\Route;
use App\Events\PedidoConfirmado;
use App\Livewire\Pedidos\Index as PedidosIndex;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\DetallePedido;

Route::get('/pedidos', PedidosIndex::class)->name('pedidos.index');

Route::get('/test-broadcast', function () {
    $sede = Sede::first();

    $pedido = Pedido::create([
        'sede_id'        => $sede?->id ?? 1,
        'fecha_pedido'   => now(),
        'hora_entrega'   => '18:00:00',
        'estado'         => 'confirmado',
        'total'          => 50000,
        'cliente_nombre' => 'TEST - Juan Pérez',
        'telefono'       => '573001234567',
        'canal'          => 'whatsapp',
        'notas'          => 'Pedido de prueba para testing',
    ]);

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

    broadcast(new PedidoConfirmado($pedido));

    return response()->json([
        'message'    => '✅ Evento disparado correctamente',
        'pedido_id'  => $pedido->id,
        'link_panel' => url('/pedidos'),
    ]);
});