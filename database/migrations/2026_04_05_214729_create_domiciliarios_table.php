<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domiciliarios', function (Blueprint $table) {
            $table->id();

            // Información básica
            $table->string('nombre');
            $table->string('telefono')->nullable();

            // Datos operativos
            $table->string('vehiculo')->nullable(); // moto, bicicleta, carro
            $table->string('placa')->nullable();

            // Estado del domiciliario
            $table->enum('estado', ['disponible', 'ocupado', 'inactivo'])
                  ->default('disponible');

            // Control
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domiciliarios');
    }
};