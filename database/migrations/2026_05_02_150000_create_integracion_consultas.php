<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consultas guardadas dentro de cada integracion.
     * Cada consulta es un query SQL parametrizado que se puede:
     *   - Ejecutar manualmente (probar)
     *   - Exponer como tool al bot agente
     */
    public function up(): void
    {
        Schema::create('integracion_consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')->constrained('integraciones')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('nombre', 80);                 // slug ej: "buscar_cliente_por_cedula"
            $table->string('nombre_publico', 150);        // display ej: "Buscar cliente por cédula"
            $table->text('descripcion')->nullable();       // qué hace (para el bot)
            $table->string('tipo', 30)->default('otros'); // clientes / productos / ventas / stock / otros

            $table->text('query_sql');                     // SQL con :param o ?
            $table->json('parametros')->nullable();        // [{nombre, tipo, descripcion, requerido}]
            $table->json('mapeo')->nullable();             // campo_destino => columna_origen (opcional)

            $table->boolean('usar_en_bot')->default(false);
            $table->boolean('activa')->default(true);

            $table->timestamp('ultima_ejecucion_at')->nullable();
            $table->unsignedInteger('total_ejecuciones')->default(0);

            $table->timestamps();

            $table->unique(['integracion_id', 'nombre']);
            $table->index(['tenant_id', 'usar_en_bot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracion_consultas');
    }
};
