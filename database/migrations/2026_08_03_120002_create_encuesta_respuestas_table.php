<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada envío/diligenciamiento de una encuesta (una fila = una persona que respondió).
 * Puede quedar ligada a un cliente/pedido, o ser anónima (link público).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('encuesta_respuestas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('encuesta_id')->constrained('encuestas')->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('token', 64)->unique();      // por si se envía dirigida (link único)
            $t->unsignedBigInteger('cliente_id')->nullable();
            $t->unsignedBigInteger('pedido_id')->nullable();
            $t->string('respondente_nombre', 150)->nullable();
            $t->timestamp('vista_at')->nullable();
            $t->timestamp('completada_at')->nullable();
            $t->timestamps();

            $t->index(['encuesta_id', 'completada_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('encuesta_respuestas'); }
};
