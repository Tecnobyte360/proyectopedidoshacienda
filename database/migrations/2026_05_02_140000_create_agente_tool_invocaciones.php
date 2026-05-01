<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra cada vez que el bot agente invoca una tool.
     * Sirve para monitoreo, debugging, métricas de uso y costo.
     */
    public function up(): void
    {
        Schema::create('agente_tool_invocaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('conversacion_id')->nullable()->constrained('conversaciones_whatsapp')->nullOnDelete();

            $table->string('tool_name', 80);          // buscar_productos, listar_categorias, etc.
            $table->string('connection_id', 50)->nullable();
            $table->string('telefono_cliente', 30)->nullable();

            $table->json('args')->nullable();          // args que pasó el LLM
            $table->json('resultado')->nullable();     // resumen del resultado (no todo, para no llenar)

            $table->unsignedSmallInteger('count_resultados')->default(0);
            $table->boolean('exitoso')->default(true);
            $table->text('error')->nullable();

            $table->unsignedInteger('latencia_ms')->default(0);
            $table->unsignedInteger('tokens_estimados')->default(0);  // input + output (si los tenemos)

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tool_name', 'created_at']);
            $table->index('conversacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_tool_invocaciones');
    }
};
