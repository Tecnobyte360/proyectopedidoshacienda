<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones_whatsapp', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->string('telefono_normalizado', 30)->index();
            $table->string('canal', 30)->default('whatsapp');

            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->unsignedBigInteger('connection_id')->nullable();

            // Estado
            $table->string('estado', 20)->default('activa');   // activa | cerrada | archivada
            $table->boolean('atendida_por_humano')->default(false);

            // Contadores cacheados
            $table->unsignedInteger('total_mensajes')->default(0);
            $table->unsignedInteger('total_mensajes_cliente')->default(0);
            $table->unsignedInteger('total_mensajes_bot')->default(0);

            // Outcomes
            $table->boolean('genero_pedido')->default(false);
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();

            // Tiempos
            $table->dateTime('primer_mensaje_at')->nullable();
            $table->dateTime('ultimo_mensaje_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'ultimo_mensaje_at']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversaciones_whatsapp');
    }
};
