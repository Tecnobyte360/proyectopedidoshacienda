<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $t) {
            // wamid del mensaje de campaña enviado a este destinatario
            $t->string('mensaje_externo_id', 200)->nullable()->after('enviado_at');

            // Acks de Meta
            $t->timestamp('entregado_at')->nullable()->after('mensaje_externo_id');
            $t->timestamp('leido_at')->nullable()->after('entregado_at');

            // Click en botón Quick Reply (texto del botón)
            $t->string('boton_click', 60)->nullable()->after('leido_at');
            $t->timestamp('boton_click_at')->nullable()->after('boton_click');

            // Reacción del cliente al mensaje de campaña
            $t->string('reaccion', 16)->nullable()->after('boton_click_at');
            $t->timestamp('reaccion_at')->nullable()->after('reaccion');

            // Conversión: pedido posterior cruzado con esta campaña
            $t->unsignedBigInteger('pedido_id')->nullable()->after('reaccion_at');
            $t->timestamp('pedido_at')->nullable()->after('pedido_id');

            $t->index('mensaje_externo_id', 'idx_camp_dest_wamid');
        });
    }

    public function down(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $t) {
            $t->dropIndex('idx_camp_dest_wamid');
            $t->dropColumn([
                'mensaje_externo_id', 'entregado_at', 'leido_at',
                'boton_click', 'boton_click_at',
                'reaccion', 'reaccion_at',
                'pedido_id', 'pedido_at',
            ]);
        });
    }
};
