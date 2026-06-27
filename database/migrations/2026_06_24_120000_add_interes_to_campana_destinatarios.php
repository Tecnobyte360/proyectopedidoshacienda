<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $table) {
            if (!Schema::hasColumn('campana_destinatarios', 'interes')) {
                $table->string('interes', 20)->nullable()->after('respuestas_count'); // interesado|no_interesado|duda
            }
            if (!Schema::hasColumn('campana_destinatarios', 'interes_motivo')) {
                $table->string('interes_motivo', 255)->nullable()->after('interes');
            }
            if (!Schema::hasColumn('campana_destinatarios', 'respuesta_texto')) {
                $table->text('respuesta_texto')->nullable()->after('interes_motivo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $table) {
            foreach (['interes','interes_motivo','respuesta_texto'] as $c) {
                if (Schema::hasColumn('campana_destinatarios', $c)) $table->dropColumn($c);
            }
        });
    }
};
