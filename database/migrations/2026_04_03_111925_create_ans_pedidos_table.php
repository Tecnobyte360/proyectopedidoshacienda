<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ans_pedidos', function (Blueprint $table) {
            $table->id();

            $table->string('accion'); 
            // crear, adicionar, cancelar

            $table->integer('tiempo_minutos'); 
            // tiempo permitido en minutos

            $table->integer('tiempo_alerta')->nullable(); 
            // opcional: alerta antes de que expire

            $table->string('descripcion')->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ans_pedidos');
    }
};