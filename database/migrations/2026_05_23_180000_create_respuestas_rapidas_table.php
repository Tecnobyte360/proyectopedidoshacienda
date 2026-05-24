<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_rapidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('atajo', 40)->nullable()->comment('Etiqueta corta visible en el chip (ej: "Saludo", "Horario")');
            $table->text('texto')->comment('Mensaje completo que se inserta al hacer click');
            $table->integer('orden')->default(0)->comment('Orden de aparición en el panel');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'activa', 'orden'], 'respuestas_rapidas_tenant_activa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_rapidas');
    }
};
