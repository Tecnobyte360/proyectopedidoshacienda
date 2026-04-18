<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // Días de anticipación: 0 = el mismo día, 1 = un día antes, etc.
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_dias_anticipacion')) {
                $table->unsignedTinyInteger('cumpleanos_dias_anticipacion')->default(0);
            }

            // Reintentos automáticos si falla el envío (0-5)
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_reintentos_max')) {
                $table->unsignedTinyInteger('cumpleanos_reintentos_max')->default(2);
            }

            // Ventana horaria: si la hora configurada cae fuera, NO manda
            // (útil como "seguro" para no mandar de madrugada por error)
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_ventana_desde')) {
                $table->string('cumpleanos_ventana_desde', 5)->default('08:00');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_ventana_hasta')) {
                $table->string('cumpleanos_ventana_hasta', 5)->default('20:00');
            }

            // Días de la semana permitidos (7 caracteres, 1=permitido 0=no)
            // orden: L M X J V S D
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_dias_semana')) {
                $table->string('cumpleanos_dias_semana', 7)->default('1111111');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn([
                'cumpleanos_dias_anticipacion',
                'cumpleanos_reintentos_max',
                'cumpleanos_ventana_desde',
                'cumpleanos_ventana_hasta',
                'cumpleanos_dias_semana',
            ]);
        });
    }
};
