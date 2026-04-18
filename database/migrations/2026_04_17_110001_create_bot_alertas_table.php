<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_alertas', function (Blueprint $table) {
            $table->id();

            // openai_credito | openai_key | openai_rate | openai_modelo |
            // openai_timeout | whatsapp_token | reverb | otro
            $table->string('tipo', 50)->index();

            // critica | warning | info
            $table->string('severidad', 20)->default('warning');

            $table->string('titulo');
            $table->text('mensaje');

            // Datos extra (status, body de respuesta, request, etc.)
            $table->json('contexto')->nullable();

            $table->unsignedSmallInteger('codigo_http')->nullable();

            // Para deduplicar — si ocurre lo mismo en 5 min, incrementa count
            $table->string('hash_dedup', 60)->nullable()->index();
            $table->unsignedInteger('ocurrencias')->default(1);

            // Estado
            $table->boolean('resuelta')->default(false);
            $table->dateTime('resuelta_at')->nullable();
            $table->string('resuelta_por')->nullable();

            $table->dateTime('vista_at')->nullable();
            $table->dateTime('ultima_ocurrencia_at')->nullable();

            $table->timestamps();

            $table->index(['resuelta', 'created_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_alertas');
    }
};
