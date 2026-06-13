<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ⏱️ ANS de ENTREGA por zona: cada zona tiene su tiempo de entrega en
        //    ruta (mín = verde, máx = rojo). Independiente del ANS global.
        Schema::create('ans_entrega_zona', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('zona_cobertura_id');
            $t->unsignedSmallInteger('minutos_min')->default(0);   // objetivo (verde)
            $t->unsignedSmallInteger('minutos_max');               // límite (rojo)
            $t->boolean('activo')->default(true);
            $t->timestamps();

            $t->unique(['zona_cobertura_id']);
            $t->foreign('zona_cobertura_id')->references('id')->on('zonas_cobertura')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ans_entrega_zona');
    }
};
