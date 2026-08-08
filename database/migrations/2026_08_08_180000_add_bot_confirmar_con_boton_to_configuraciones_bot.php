<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'bot_confirmar_con_boton')) {
                // 🔘 Cuando está ON, el bot muestra el resumen del pedido (armado
                // desde el carrito real) con botones interactivos de WhatsApp
                // (Confirmar / Modificar / Cancelar) en vez de pedir confirmación
                // por texto libre. Elimina la ambigüedad del "Si".
                $table->boolean('bot_confirmar_con_boton')
                    ->default(false)
                    ->after('bot_ofrece_recoger');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'bot_confirmar_con_boton')) {
                $table->dropColumn('bot_confirmar_con_boton');
            }
        });
    }
};
