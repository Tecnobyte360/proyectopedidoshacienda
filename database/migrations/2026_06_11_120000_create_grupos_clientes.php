<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 👥 Grupos de clientes (listas dinámicas para difusión por plantilla)
        Schema::create('grupos_clientes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('nombre');
            $t->string('descripcion')->nullable();
            $t->string('color', 20)->default('#d68643'); // chip de color
            $t->timestamps();
        });

        // Pivote grupo ↔ cliente
        Schema::create('cliente_grupo', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('grupo_id');
            $t->unsignedBigInteger('cliente_id');
            $t->timestamps();

            $t->unique(['grupo_id', 'cliente_id']);
            $t->index('cliente_id');
            $t->foreign('grupo_id')->references('id')->on('grupos_clientes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_grupo');
        Schema::dropIfExists('grupos_clientes');
    }
};
