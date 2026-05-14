<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'watchdog_activo')) {
                $table->boolean('watchdog_activo')->default(true)->after('auto_limpieza_hora')
                    ->comment('Activar el watchdog de conversaciones estancadas');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'watchdog_min_segundos')) {
                $table->unsignedSmallInteger('watchdog_min_segundos')->default(30)->after('watchdog_activo')
                    ->comment('Tiempo mínimo (seg) sin respuesta del bot antes de rescatar');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'watchdog_max_minutos')) {
                $table->unsignedSmallInteger('watchdog_max_minutos')->default(5)->after('watchdog_min_segundos')
                    ->comment('Tiempo máximo (min) — mensajes más viejos NO se rescatan');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'watchdog_skip_pedido_min')) {
                $table->unsignedSmallInteger('watchdog_skip_pedido_min')->default(30)->after('watchdog_max_minutos')
                    ->comment('Si cliente tiene pedido <X min, NO rescatar (evita duplicados)');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'watchdog_cooldown_conv_min')) {
                $table->unsignedSmallInteger('watchdog_cooldown_conv_min')->default(30)->after('watchdog_skip_pedido_min')
                    ->comment('Cooldown entre rescates de la misma conversación (minutos)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $cols = ['watchdog_activo', 'watchdog_min_segundos', 'watchdog_max_minutos', 'watchdog_skip_pedido_min', 'watchdog_cooldown_conv_min'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
