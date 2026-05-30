<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $t) {
            // Historial completo de clicks de botones del destinatario.
            // Estructura: [{"boton": "Quiero pedir", "at": "2026-05-30 11:34:36"}, ...]
            $t->json('botones_clicks')->nullable()->after('boton_click_at');
        });
    }

    public function down(): void
    {
        Schema::table('campana_destinatarios', function (Blueprint $t) {
            $t->dropColumn('botones_clicks');
        });
    }
};
