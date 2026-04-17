<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('categoria_id')
                ->nullable()
                ->constrained('productos_categorias')
                ->nullOnDelete();

            $table->string('codigo')->nullable()->index();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('descripcion_corta')->nullable();

            $table->string('unidad', 32)->default('unidad');
            $table->decimal('precio_base', 12, 2)->default(0);

            $table->string('imagen_url')->nullable();
            $table->json('palabras_clave')->nullable();

            $table->boolean('activo')->default(true);
            $table->boolean('destacado')->default(false);
            $table->unsignedInteger('orden')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'destacado']);
            $table->index(['categoria_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
