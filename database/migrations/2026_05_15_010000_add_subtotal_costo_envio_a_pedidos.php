<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🚚 Agrega `subtotal` y `costo_envio` a la tabla pedidos.
 *
 * Antes, la columna `total` guardaba la suma final pero NO había desglose:
 * - subtotal: suma de productos sin envío
 * - costo_envio: lo que se cobró por domicilio
 *
 * Esto causaba que el costo de envío se perdiera al guardar, y los pedidos
 * a domicilio se cobraban como si fueran pickup. Bug crítico que afectaba
 * la facturación.
 *
 * También agrega beneficio_cliente_id para auditar cuándo se aplicó un
 * beneficio (envío gratis por cumpleaños, descuento, etc).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            if (!Schema::hasColumn('pedidos', 'subtotal')) {
                $t->decimal('subtotal', 12, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('pedidos', 'costo_envio')) {
                $t->decimal('costo_envio', 10, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('pedidos', 'beneficio_cliente_id')) {
                $t->unsignedBigInteger('beneficio_cliente_id')->nullable()->after('costo_envio');
                // FK opcional: si beneficios_clientes existe, ligar
                if (Schema::hasTable('beneficios_clientes')) {
                    $t->foreign('beneficio_cliente_id')
                        ->references('id')
                        ->on('beneficios_clientes')
                        ->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            if (Schema::hasColumn('pedidos', 'beneficio_cliente_id')) {
                try { $t->dropForeign(['beneficio_cliente_id']); } catch (\Throwable $e) { /* ignore */ }
                $t->dropColumn('beneficio_cliente_id');
            }
            if (Schema::hasColumn('pedidos', 'costo_envio')) {
                $t->dropColumn('costo_envio');
            }
            if (Schema::hasColumn('pedidos', 'subtotal')) {
                $t->dropColumn('subtotal');
            }
        });
    }
};
