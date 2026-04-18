<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Events\PedidoConfirmado;
use App\Livewire\Pedidos\Index as PedidosIndex;
use App\Livewire\Pedidos\SeguimientoPedido;
use App\Livewire\Productos\Index as ProductosIndex;
use App\Livewire\Categorias\Index as CategoriasIndex;
use App\Livewire\Promociones\Index as PromocionesIndex;
use App\Livewire\Domiciliarios as DomiciliariosIndex;
use App\Livewire\Reportes\Index as ReportesIndex;
use App\Livewire\Zonas\Index as ZonasIndex;
use App\Livewire\Despachos\Index as DespachosIndex;
use App\Livewire\Ans\Index as AnsIndex;
use App\Livewire\Configuracion\Bot as ConfiguracionBot;
use App\Models\Sede;
use App\Models\Pedido;
use App\Models\DetallePedido;

Route::get('/pedidos', PedidosIndex::class)->name('pedidos.index');

Route::get('/productos',     ProductosIndex::class)->name('productos.index');
Route::get('/categorias',    CategoriasIndex::class)->name('categorias.index');
Route::get('/promociones',   PromocionesIndex::class)->name('promociones.index');
Route::get('/domiciliarios', DomiciliariosIndex::class)->name('domiciliarios.index');
Route::get('/zonas',         ZonasIndex::class)->name('zonas.index');
Route::get('/despachos',     DespachosIndex::class)->name('despachos.index');
Route::get('/reportes',      ReportesIndex::class)->name('reportes.index');
Route::get('/ans-tiempos',     AnsIndex::class)->name('ans.index');
Route::get('/configuracion/bot', ConfiguracionBot::class)->name('configuracion.bot');

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
