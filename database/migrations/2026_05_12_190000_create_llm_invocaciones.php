<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('llm_invocaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('conversacion_id')->nullable()->index();
            $table->string('telefono', 30)->nullable()->index();

            // Modelo y proveedor
            $table->string('provider', 20)->default('anthropic');   // anthropic | openai
            $table->string('modelo', 64)->nullable();               // claude-haiku-4-5
            $table->boolean('es_fallback')->default(false);         // true si fue intento de fallback

            // Resultado HTTP
            $table->unsignedSmallInteger('http_status')->nullable(); // 200, 429, 529, 400, etc.
            $table->boolean('exitoso')->default(false);
            $table->string('error_tipo', 64)->nullable();            // rate_limit_error, overloaded_error, etc.
            $table->text('error_mensaje')->nullable();

            // Tokens (si Anthropic los devuelve)
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->unsignedInteger('tokens_cache_read')->nullable();
            $table->unsignedInteger('tokens_cache_creation')->nullable();

            // Performance
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->unsignedTinyInteger('intentos')->default(1);    // numero de reintentos

            // Contexto del request
            $table->unsignedSmallInteger('messages_count')->nullable();
            $table->unsignedSmallInteger('tools_count')->nullable();

            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['http_status', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_invocaciones');
    }
};
