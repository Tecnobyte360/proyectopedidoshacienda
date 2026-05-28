<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            // 📥 Marca mensajes que entraron por importación manual (export WA del cel)
            // — útil para filtrar, mostrar badge, y excluir del cálculo de costos Meta.
            $table->boolean('importado_historico')->default(false)->after('mensaje_externo_id');
            $table->string('fuente_importacion', 40)->nullable()->after('importado_historico');
            // Ej: 'wa_export_android', 'wa_export_ios', 'tecnobyteapp_sync', 'csv'

            $table->index(['conversacion_id', 'importado_historico']);
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->dropIndex(['conversacion_id', 'importado_historico']);
            $table->dropColumn(['importado_historico', 'fuente_importacion']);
        });
    }
};
