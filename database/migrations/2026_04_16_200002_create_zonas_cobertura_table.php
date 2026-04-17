<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_cobertura', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')
                ->nullable()
                ->constrained('sedes')
                ->nullOnDelete();

            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('color', 16)->default('#d68643');
            $table->decimal('costo_envio', 12, 2)->default(0);
            $table->unsignedSmallInteger('tiempo_estimado_min')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);

            $table->timestamps();

            $table->index(['sede_id', 'activa']);
        });

        Schema::create('zona_barrios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('zona_cobertura_id')
                ->constrained('zonas_cobertura')
                ->cascadeOnDelete();

            $table->string('nombre');
            $table->string('nombre_normalizado')->index();
            $table->timestamps();

            $table->unique(['zona_cobertura_id', 'nombre_normalizado']);
        });

        Schema::create('domiciliario_zona_cobertura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domiciliario_id')->constrained('domiciliarios')->cascadeOnDelete();
            $table->foreignId('zona_cobertura_id')->constrained('zonas_cobertura')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['domiciliario_id', 'zona_cobertura_id'], 'dom_zona_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domiciliario_zona_cobertura');
        Schema::dropIfExists('zona_barrios');
        Schema::dropIfExists('zonas_cobertura');
    }
};
