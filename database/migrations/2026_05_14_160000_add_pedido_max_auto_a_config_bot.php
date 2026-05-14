<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            if (!Schema::hasColumn('configuraciones_bot', 'pedido_max_auto')) {
                $t->unsignedInteger('pedido_max_auto')->default(500000)
                    ->comment('Pedidos > este monto se derivan a humano antes de confirmar. 0 desactiva.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            if (Schema::hasColumn('configuraciones_bot', 'pedido_max_auto')) {
                $t->dropColumn('pedido_max_auto');
            }
        });
    }
};
