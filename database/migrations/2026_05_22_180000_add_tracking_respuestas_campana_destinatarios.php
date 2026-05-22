<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $table) {
            // Tracking de respuestas para medir conversión real de la campaña
            $table->timestamp('respondio_at')->nullable()->after('enviado_at')
                ->comment('Cuándo el cliente respondió al mensaje de la campaña por primera vez');

            // Conteo de respuestas (algunos clientes mandan varios mensajes)
            $table->unsignedInteger('respuestas_count')->default(0)->after('respondio_at');

            $table->index(['campana_id', 'respondio_at'], 'idx_campana_respondio');
            $table->index('telefono', 'idx_telefono_dest');
        });
    }

    public function down(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $table) {
            $table->dropIndex('idx_campana_respondio');
            $table->dropIndex('idx_telefono_dest');
            $table->dropColumn(['respondio_at', 'respuestas_count']);
        });
    }
};
