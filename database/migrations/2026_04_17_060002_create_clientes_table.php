<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('pais_codigo', 6)->default('+57');
            $table->string('telefono', 30)->index();
            $table->string('telefono_normalizado', 30)->unique();   // p.ej. 573001234567
            $table->string('email')->nullable();

            // Ubicación principal
            $table->string('direccion_principal')->nullable();
            $table->string('barrio')->nullable();
            $table->foreignId('zona_cobertura_id')->nullable()->constrained('zonas_cobertura')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Notas y preferencias (libre)
            $table->text('notas_internas')->nullable();
            $table->json('preferencias')->nullable();   // ej: ["sin sal", "siempre paga efectivo"]

            // Métricas (cacheadas — se actualizan al confirmar pedidos)
            $table->unsignedInteger('total_pedidos')->default(0);
            $table->decimal('total_gastado', 14, 2)->default(0);
            $table->decimal('ticket_promedio', 12, 2)->default(0);
            $table->dateTime('fecha_primer_pedido')->nullable();
            $table->dateTime('fecha_ultimo_pedido')->nullable();

            // Origen y estado
            $table->string('canal_origen', 30)->default('whatsapp');   // whatsapp | web | manual
            $table->boolean('activo')->default(true);

            // Multi-empresa (si aplica)
            $table->unsignedBigInteger('empresa_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'fecha_ultimo_pedido']);
            $table->index('barrio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
