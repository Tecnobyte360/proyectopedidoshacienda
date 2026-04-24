<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'derivacion_activa')) {
                $table->boolean('derivacion_activa')->default(true)->after('saludar_con_promociones');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'derivacion_instrucciones_ia')) {
                $table->text('derivacion_instrucciones_ia')->nullable()->after('derivacion_activa');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'derivacion_fallback_activa')) {
                $table->boolean('derivacion_fallback_activa')->default(true)->after('derivacion_instrucciones_ia');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'derivacion_frases_deteccion')) {
                $table->text('derivacion_frases_deteccion')->nullable()->after('derivacion_fallback_activa');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'derivacion_departamento_fallback_id')) {
                $table->unsignedBigInteger('derivacion_departamento_fallback_id')->nullable()->after('derivacion_frases_deteccion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            foreach ([
                'derivacion_activa',
                'derivacion_instrucciones_ia',
                'derivacion_fallback_activa',
                'derivacion_frases_deteccion',
                'derivacion_departamento_fallback_id',
            ] as $col) {
                if (Schema::hasColumn('configuraciones_bot', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
