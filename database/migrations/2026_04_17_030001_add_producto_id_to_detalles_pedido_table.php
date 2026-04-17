<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            $table->foreignId('producto_id')
                ->nullable()
                ->after('pedido_id')
                ->constrained('productos')
                ->nullOnDelete();

            $table->string('codigo_producto')->nullable()->after('producto');
        });
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn(['producto_id', 'codigo_producto']);
        });
    }
};
