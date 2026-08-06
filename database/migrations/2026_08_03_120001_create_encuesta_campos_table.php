<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos (preguntas) que el tenant define para una encuesta.
 * tipo: estrellas | texto | textarea | radio | checkbox | si_no | numero
 * opciones: JSON con la lista de opciones (solo radio/checkbox) o config (ej. max estrellas)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('encuesta_campos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('encuesta_id')->constrained('encuestas')->cascadeOnDelete();
            $t->string('etiqueta');
            $t->string('tipo', 20);
            $t->json('opciones')->nullable();
            $t->string('placeholder', 150)->nullable();
            $t->boolean('requerido')->default(false);
            $t->unsignedInteger('orden')->default(0);
            $t->timestamps();

            $t->index(['encuesta_id', 'orden']);
        });
    }

    public function down(): void { Schema::dropIfExists('encuesta_campos'); }
};
