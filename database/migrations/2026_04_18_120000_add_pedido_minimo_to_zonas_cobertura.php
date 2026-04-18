<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas_cobertura', function (Blueprint $table) {
            // Valor mínimo de pedido para domicilio en esta zona.
            // 0 = sin mínimo (acepta cualquier pedido).
            $table->decimal('pedido_minimo', 10, 2)->default(0)->after('costo_envio');
        });
    }

    public function down(): void
    {
        Schema::table('zonas_cobertura', function (Blueprint $table) {
            $table->dropColumn('pedido_minimo');
        });
    }
};
