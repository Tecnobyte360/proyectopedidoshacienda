<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'auto_asignar_domiciliario')) {
                $table->boolean('auto_asignar_domiciliario')->default(false)->after('enviar_link_pago');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'criterio_asignacion')) {
                // 'balanceado' | 'cercania' | 'rotacion'
                $table->string('criterio_asignacion', 20)->default('balanceado')->after('auto_asignar_domiciliario');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'asignar_en_estado')) {
                // 'en_preparacion' (al confirmar) | 'listo' (cuando el operador marca listo)
                $table->string('asignar_en_estado', 30)->default('en_preparacion')->after('criterio_asignacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            foreach (['auto_asignar_domiciliario', 'criterio_asignacion', 'asignar_en_estado'] as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
