<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watchdog_rescates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('conversacion_id')->index();
            $table->string('telefono', 20)->nullable()->index();
            $table->unsignedBigInteger('mensaje_origen_id')->nullable()
                ->comment('ID del mensaje del cliente que disparó el rescate');
            $table->text('mensaje_contenido')->nullable()
                ->comment('Contenido del mensaje del cliente reenviado');
            $table->unsignedInteger('segundos_estancada')
                ->comment('Cuántos segundos llevaba sin respuesta del bot');
            $table->boolean('exitoso')->default(false)
                ->comment('Si el reenvío al webhook fue 200 OK');
            $table->string('error_mensaje', 500)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchdog_rescates');
    }
};
