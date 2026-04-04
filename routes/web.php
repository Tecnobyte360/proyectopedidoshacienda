<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Events\PedidoConfirmado;
use App\Livewire\Pedidos\Index as PedidosIndex;
use App\Livewire\Pedidos\SeguimientoPedido;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\DetallePedido;

Route::get('/pedidos', PedidosIndex::class)->name('pedidos.index');

Route::get('/seguimiento-pedido/{codigo}', SeguimientoPedido::class)
    ->name('pedidos.seguimiento');

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
        'telefono'       => '573001234567',
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
