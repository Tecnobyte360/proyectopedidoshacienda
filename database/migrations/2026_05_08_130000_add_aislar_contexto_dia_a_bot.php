<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // 🛡️ Si está activo, solo se envían a OpenAI mensajes de HOY.
            // Mensajes de días previos quedan en BD intactos pero no
            // contaminan el contexto del bot.
            $table->boolean('aislar_contexto_por_dia')->default(true)
                ->after('auto_reset_horas_inactividad')
                ->comment('Solo enviar a la IA mensajes del día actual (Bogotá). Historial en BD se conserva.');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('aislar_contexto_por_dia');
        });
    }
};
