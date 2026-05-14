<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            if (!Schema::hasColumn('configuraciones_bot', 'memoria_msgs_max')) {
                $t->unsignedSmallInteger('memoria_msgs_max')->default(50)
                    ->comment('Cuántos mensajes recientes recibe el LLM (memoria conversacional)');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'memoria_chars_max')) {
                $t->unsignedInteger('memoria_chars_max')->default(80000)
                    ->comment('Tamaño máximo total del historial en chars (~tokens × 4)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            foreach (['memoria_msgs_max', 'memoria_chars_max'] as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
