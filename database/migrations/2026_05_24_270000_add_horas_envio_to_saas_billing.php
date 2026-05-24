<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⏰ Horarios configurables de envío de recordatorios SaaS.
 *
 * - saas_horas_envio: JSON array de HH:MM (ej. ["09:00","14:00","18:00"])
 *   El scheduler corre cada minuto y dispara los crons cuando el HH:MM
 *   actual coincide con cualquiera de esas horas. Permite N envíos/día.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_horas_envio')) {
                $t->json('saas_horas_envio')->nullable()->after('saas_dias_gracia');
            }
        });

        // Default: una sola hora a las 10:00
        \DB::table('configuracion_plataforma')->update([
            'saas_horas_envio' => json_encode(['10:00']),
        ]);
    }

    public function down(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            if (Schema::hasColumn('configuracion_plataforma', 'saas_horas_envio')) {
                $t->dropColumn('saas_horas_envio');
            }
        });
    }
};
