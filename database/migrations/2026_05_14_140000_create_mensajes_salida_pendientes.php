<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mensajes_salida_pendientes')) return;

        Schema::create('mensajes_salida_pendientes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable()->index();
            $t->unsignedBigInteger('conversacion_id')->nullable()->index();
            $t->string('telefono', 32);
            $t->unsignedInteger('connection_id')->nullable();
            $t->unsignedInteger('whatsapp_id')->nullable();
            $t->json('payload'); // body, mediaUrl, replyTo, etc.
            $t->unsignedSmallInteger('intentos')->default(0);
            $t->text('ultimo_error')->nullable();
            $t->timestamp('proximo_intento_at')->nullable()->index();
            $t->timestamp('enviado_at')->nullable()->index();
            $t->timestamp('fallido_permanente_at')->nullable();
            $t->timestamps();

            $t->index(['enviado_at', 'fallido_permanente_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_salida_pendientes');
    }
};
