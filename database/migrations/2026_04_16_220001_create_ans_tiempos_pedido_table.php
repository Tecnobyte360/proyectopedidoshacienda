<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ans_tiempos_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('estado', 60)->unique();   // nuevo | en_preparacion | repartidor_en_camino
            $table->string('nombre');                  // "Atención inicial", "Preparación", "Entrega"
            $table->string('descripcion')->nullable();

            $table->unsignedSmallInteger('minutos_objetivo');  // hasta acá → VERDE
            $table->unsignedSmallInteger('minutos_alerta');    // de acá → AMARILLO
            $table->unsignedSmallInteger('minutos_critico');   // de acá → ROJO

            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ans_tiempos_pedido');
    }
};
