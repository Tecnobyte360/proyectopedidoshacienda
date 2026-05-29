<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            // 📌 Conversación fijada: aparece arriba del listado.
            // Guardamos timestamp para ordenar entre varias fijadas (más recientes primero).
            $table->dateTime('fijada_at')->nullable()->after('atendida_por_humano');

            // 👁️ Marcada como no leída manualmente por el operador.
            // Es un override sobre no_leidos: incluso si la lee, sigue resaltada hasta que se desmarque.
            $table->boolean('marcada_no_leida')->default(false)->after('fijada_at');

            $table->index(['fijada_at']);
            $table->index(['marcada_no_leida']);
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            $table->dropIndex(['fijada_at']);
            $table->dropIndex(['marcada_no_leida']);
            $table->dropColumn(['fijada_at', 'marcada_no_leida']);
        });
    }
};
