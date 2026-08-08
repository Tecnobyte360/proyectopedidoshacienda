<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutivo de pedido POR TENANT (cada empresa su propia numeración).
 * El `id` global se conserva; `numero_pedido` es lo que se muestra al cliente.
 * La Hacienda (tenant 1) se backfillea con numero_pedido = id para no cambiar
 * los números que ya están en producción.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->unsignedInteger('numero_pedido')->nullable()->after('id');
            $t->index(['tenant_id', 'numero_pedido']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'numero_pedido']);
            $t->dropColumn('numero_pedido');
        });
    }
};
