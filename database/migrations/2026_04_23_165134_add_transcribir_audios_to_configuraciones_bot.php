<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag para activar/desactivar la transcripción de audios entrantes con Whisper.
 * Cuando está activa, el bot recibe notas de voz, las transcribe a texto
 * y las procesa como si el cliente hubiera escrito.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'transcribir_audios')) {
                $table->boolean('transcribir_audios')
                      ->default(true)
                      ->after('enviar_imagenes_productos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'transcribir_audios')) {
                $table->dropColumn('transcribir_audios');
            }
        });
    }
};
