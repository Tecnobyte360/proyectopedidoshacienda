<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('icono_emoji', 8)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_categorias');
    }
};
