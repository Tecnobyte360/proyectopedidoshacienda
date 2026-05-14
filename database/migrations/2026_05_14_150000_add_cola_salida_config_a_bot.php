<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            if (!Schema::hasColumn('configuraciones_bot', 'cola_salida_activa')) {
                $t->boolean('cola_salida_activa')->default(true)
                    ->comment('Si false, los envíos fallidos NO se encolan (vuelve al comportamiento legacy)');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cola_salida_max_intentos')) {
                $t->unsignedSmallInteger('cola_salida_max_intentos')->default(12)
                    ->comment('Tras N intentos fallidos, marcar fallido_permanente_at');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cola_salida_backoff_segundos')) {
                $t->json('cola_salida_backoff_segundos')->nullable()
                    ->comment('Array de segundos por intento. Ej [15,30,60,120,300,900,3600,21600]');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cola_salida_email_alerta')) {
                $t->string('cola_salida_email_alerta')->nullable()
                    ->comment('Email donde se avisa cuando un mensaje falla permanentemente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            foreach (['cola_salida_activa', 'cola_salida_max_intentos', 'cola_salida_backoff_segundos', 'cola_salida_email_alerta'] as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
