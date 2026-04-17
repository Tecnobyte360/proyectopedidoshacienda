<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocion_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promocion_id', 'producto_id']);
        });

        Schema::create('promocion_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promocion_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion_sede');
        Schema::dropIfExists('promocion_producto');
    }
};
