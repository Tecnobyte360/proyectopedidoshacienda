<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // JSON con la lista ordenada de campos que el bot pedirá:
            // [
            //   {"campo":"cedula","activo":true,"orden":1},
            //   {"campo":"producto","activo":true,"orden":2},
            //   {"campo":"direccion","activo":true,"orden":3},
            //   ...
            // ]
            $table->json('flujo_pedido_orden')->nullable()
                ->after('aceptar_pedidos_fuera_horario')
                ->comment('Orden y campos que el bot pide al armar un pedido');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('flujo_pedido_orden');
        });
    }
};
