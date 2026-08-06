<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite adjuntar un archivo (PDF o imagen) a una respuesta rápida.
 * Al usarla en el chat, se envía el archivo con el texto como caption.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->string('adjunto_path')->nullable()->after('texto');   // ruta en disco public
            $t->string('adjunto_nombre', 200)->nullable()->after('adjunto_path'); // nombre original
            $t->string('adjunto_mime', 100)->nullable()->after('adjunto_nombre');
            $t->string('adjunto_tipo', 20)->nullable()->after('adjunto_mime');     // image | document
        });
    }

    public function down(): void
    {
        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->dropColumn(['adjunto_path', 'adjunto_nombre', 'adjunto_mime', 'adjunto_tipo']);
        });
    }
};
