<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_ivr', function (Blueprint $t) {
            if (!Schema::hasColumn('conversaciones_ivr', 'carrito')) {
                $t->json('carrito')->nullable();          // [{producto_id, nombre, cantidad, precio, subtotal}]
            }
            if (!Schema::hasColumn('conversaciones_ivr', 'direccion_entrega')) {
                $t->string('direccion_entrega', 255)->nullable();
            }
            if (!Schema::hasColumn('conversaciones_ivr', 'pedido_creado_id')) {
                $t->foreignId('pedido_creado_id')->nullable()->constrained('pedidos')->nullOnDelete();
            }
        });

        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'ivr_puede_crear_pedidos')) {
                $t->boolean('ivr_puede_crear_pedidos')->default(true);
            }
            if (!Schema::hasColumn('tenants', 'ivr_notificar_whatsapp_operador')) {
                $t->string('ivr_notificar_whatsapp_operador', 20)->nullable()
                  ->comment('Tel WhatsApp donde notificar pedidos creados por IVR');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_ivr', function (Blueprint $t) {
            foreach (['carrito','direccion_entrega','pedido_creado_id'] as $c) {
                if (Schema::hasColumn('conversaciones_ivr', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['ivr_puede_crear_pedidos','ivr_notificar_whatsapp_operador'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });
    }
};
