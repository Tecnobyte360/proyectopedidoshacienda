<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversacion_id')
                ->constrained('conversaciones_whatsapp')
                ->cascadeOnDelete();

            // Rol: user | assistant | system | tool
            $table->string('rol', 20);

            // Tipo: text | image | audio | tool_call | function_result
            $table->string('tipo', 30)->default('text');

            // Contenido principal
            $table->text('contenido')->nullable();

            // Metadata adicional (tool_calls, function name, audio url, etc)
            $table->json('meta')->nullable();

            // ID del mensaje en WhatsApp (para anti-duplicados)
            $table->string('mensaje_externo_id')->nullable()->index();

            // Latencia de respuesta (para mensajes assistant)
            $table->unsignedInteger('latencia_ms')->nullable();

            // Tokens usados (estimado, para mensajes assistant)
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();

            $table->timestamps();

            $table->index(['conversacion_id', 'created_at']);
            $table->index(['rol', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
