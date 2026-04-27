<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'notif_pedido_confirmado_mensaje')) {
                $table->text('notif_pedido_confirmado_mensaje')->nullable()->after('notif_pago_rechazado_mensaje');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'notif_pedido_confirmado_mensaje')) {
                $table->dropColumn('notif_pedido_confirmado_mensaje');
            }
        });
    }
};
