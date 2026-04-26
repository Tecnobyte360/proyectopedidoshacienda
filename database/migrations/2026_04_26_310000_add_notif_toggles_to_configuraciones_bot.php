<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // Toggles ON/OFF para cada notificación que el bot manda al cliente.
            // Por defecto todo en true para no cambiar el comportamiento existente.
            foreach ([
                'notif_en_preparacion_activa',
                'notif_en_camino_activa',
                'notif_entregado_activa',
                'notif_pago_aprobado_activa',
                'notif_pago_rechazado_activa',
            ] as $col) {
                if (!Schema::hasColumn('configuraciones_bot', $col)) {
                    $table->boolean($col)->default(true)->after('encuesta_mensaje');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            foreach ([
                'notif_en_preparacion_activa',
                'notif_en_camino_activa',
                'notif_entregado_activa',
                'notif_pago_aprobado_activa',
                'notif_pago_rechazado_activa',
            ] as $col) {
                if (Schema::hasColumn('configuraciones_bot', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
