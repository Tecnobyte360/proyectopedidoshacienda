<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flujos_bot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('prioridad')->default(0);
            $table->json('grafo')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'activo', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flujos_bot');
    }
};
