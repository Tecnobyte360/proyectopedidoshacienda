<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // Plantillas editables (texto del mensaje, con variables como {nombre},
            // {pedido}, {token}, {total}, etc.). Si está vacío, se usa el default
            // hardcoded como fallback.
            $cols = [
                'notif_en_preparacion_mensaje',
                'notif_en_camino_mensaje',
                'notif_entregado_mensaje',
                'notif_pago_aprobado_mensaje',
                'notif_pago_rechazado_mensaje',
            ];
            foreach ($cols as $c) {
                if (!Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->text($c)->nullable()->after('notif_pago_rechazado_activa');
                }
            }

            // Delay en segundos antes de enviar cada notificación. 0 = inmediato.
            // Util para "reordenar" mensajes (ej. pago aprobado con 0s, encuesta
            // con 120s, etc.).
            $delays = [
                'notif_en_preparacion_delay',
                'notif_en_camino_delay',
                'notif_entregado_delay',
                'notif_pago_aprobado_delay',
                'notif_pago_rechazado_delay',
            ];
            foreach ($delays as $c) {
                if (!Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->integer($c)->default(0)->after('notif_pago_rechazado_activa');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            foreach ([
                'notif_en_preparacion_mensaje', 'notif_en_camino_mensaje', 'notif_entregado_mensaje',
                'notif_pago_aprobado_mensaje', 'notif_pago_rechazado_mensaje',
                'notif_en_preparacion_delay', 'notif_en_camino_delay', 'notif_entregado_delay',
                'notif_pago_aprobado_delay', 'notif_pago_rechazado_delay',
            ] as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
