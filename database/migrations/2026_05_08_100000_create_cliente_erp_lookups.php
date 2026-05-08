<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_erp_lookups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('integracion_id')->index();
            $table->unsignedBigInteger('pedido_id')->nullable()->index();

            $table->string('accion', 20); // buscar / crear
            $table->boolean('encontrado')->default(false); // solo para 'buscar'
            $table->boolean('exitoso')->default(true); // sin errores

            $table->string('cedula', 30)->nullable()->index();
            $table->string('telefono', 30)->nullable();
            $table->string('nombre', 200)->nullable();
            $table->string('direccion', 500)->nullable();

            $table->json('datos_cliente_erp')->nullable(); // datos completos retornados por el ERP
            $table->text('error_mensaje')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_erp_lookups');
    }
};
