<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->string('descripcion')->nullable();

            // porcentaje | monto_fijo | precio_especial | nx1
            $table->string('tipo', 32)->default('porcentaje');

            $table->decimal('valor', 12, 2)->default(0);
            $table->unsignedSmallInteger('compra')->nullable();
            $table->unsignedSmallInteger('paga')->nullable();

            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();

            $table->string('imagen_url')->nullable();
            $table->string('codigo_cupon')->nullable()->unique();

            $table->boolean('activa')->default(true);
            $table->boolean('aplica_todos_productos')->default(false);
            $table->boolean('aplica_todas_sedes')->default(true);

            $table->unsignedInteger('orden')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activa', 'fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
