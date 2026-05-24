<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚙️ Política de cobros SaaS configurable.
 *
 * Permite al admin Kivox decidir desde la UI:
 *  - Cuántos días antes del vencimiento se crea la factura + manda link
 *  - Cuántos días de gracia tras vencer antes de suspender
 *  - Qué recordatorios escalonados están activos
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            // Política básica
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_dias_antes_factura')) {
                $t->unsignedSmallInteger('saas_dias_antes_factura')->default(7)->after('saas_wompi_redirect_url');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_dias_gracia')) {
                $t->unsignedSmallInteger('saas_dias_gracia')->default(7)->after('saas_dias_antes_factura');
            }

            // Toggles de etapas de recordatorio (true = activo)
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_aviso_preaviso')) {
                $t->boolean('saas_aviso_preaviso')->default(true)->after('saas_dias_gracia');     // día -3
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_aviso_vence_hoy')) {
                $t->boolean('saas_aviso_vence_hoy')->default(true)->after('saas_aviso_preaviso'); // día 0
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_aviso_vencio_ayer')) {
                $t->boolean('saas_aviso_vencio_ayer')->default(true)->after('saas_aviso_vence_hoy'); // día +1
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_aviso_urgencia')) {
                $t->boolean('saas_aviso_urgencia')->default(true)->after('saas_aviso_vencio_ayer'); // día +3
            }

            // Plantillas de mensaje editables (texto libre)
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_mensaje_factura')) {
                $t->text('saas_mensaje_factura')->nullable()->after('saas_aviso_urgencia');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_mensaje_suspendido')) {
                $t->text('saas_mensaje_suspendido')->nullable()->after('saas_mensaje_factura');
            }

            // Activar/desactivar globalmente los crons
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_billing_activo')) {
                $t->boolean('saas_billing_activo')->default(true)->after('saas_mensaje_suspendido');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            foreach ([
                'saas_dias_antes_factura', 'saas_dias_gracia',
                'saas_aviso_preaviso', 'saas_aviso_vence_hoy', 'saas_aviso_vencio_ayer', 'saas_aviso_urgencia',
                'saas_mensaje_factura', 'saas_mensaje_suspendido',
                'saas_billing_activo',
            ] as $c) {
                if (Schema::hasColumn('configuracion_plataforma', $c)) $t->dropColumn($c);
            }
        });
    }
};
