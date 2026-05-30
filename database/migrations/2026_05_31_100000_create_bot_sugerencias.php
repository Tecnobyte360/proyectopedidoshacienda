<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bot_sugerencias', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('conversacion_id')->index();
            $t->unsignedBigInteger('mensaje_cliente_id')->nullable()
                ->comment('último mensaje del cliente que disparó la sugerencia');

            $t->longText('sugerencia');                       // lo que el bot propuso
            $t->longText('respuesta_operador')->nullable();   // lo que el operador realmente envió

            // pendiente | usada | editada | ignorada
            $t->string('estado', 20)->default('pendiente')->index();
            $t->unsignedTinyInteger('similitud')->nullable()  // 0-100 entre sugerencia y lo enviado
                ->comment('% de parecido cuando el operador edita');

            $t->timestamp('decidido_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sugerencias');
    }
};
