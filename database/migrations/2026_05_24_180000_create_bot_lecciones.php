<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_lecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('categoria', 60)->index();                 // 'cantidad', 'cobertura', 'producto', 'general', 'precio', 'direccion'
            $table->string('titulo', 200);                            // resumen 1 línea
            $table->text('contexto_error')->nullable();               // qué hizo mal el bot
            $table->text('regla')->nullable();                        // qué debe hacer en su lugar
            $table->string('frase_disparadora', 200)->nullable();    // ej. "cuando cliente dice 'recojo'"
            $table->foreignId('conversacion_id')->nullable()->constrained('conversaciones_whatsapp')->nullOnDelete();
            $table->foreignId('mensaje_id')->nullable()->constrained('mensajes_whatsapp')->nullOnDelete();
            $table->foreignId('reportado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('activa')->default(true)->index();
            $table->integer('veces_aplicada')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'activa', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_lecciones');
    }
};
