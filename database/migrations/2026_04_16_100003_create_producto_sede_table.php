<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_sede', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnDelete();

            $table->decimal('precio', 12, 2)->nullable();
            $table->boolean('disponible')->default(true);
            $table->text('nota_sede')->nullable();

            $table->timestamps();

            $table->unique(['producto_id', 'sede_id']);
            $table->index(['sede_id', 'disponible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_sede');
    }
};
