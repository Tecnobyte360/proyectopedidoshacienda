<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor respondido para CADA campo de una respuesta.
 * `valor` guarda el escalar (texto/numero/estrellas/si-no) o JSON (checkbox multi).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('encuesta_respuesta_valores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('encuesta_respuesta_id')->constrained('encuesta_respuestas')->cascadeOnDelete();
            $t->foreignId('encuesta_campo_id')->constrained('encuesta_campos')->cascadeOnDelete();
            $t->text('valor')->nullable();
            $t->timestamps();

            $t->index('encuesta_respuesta_id');
            $t->index('encuesta_campo_id');
        });
    }

    public function down(): void { Schema::dropIfExists('encuesta_respuesta_valores'); }
};
