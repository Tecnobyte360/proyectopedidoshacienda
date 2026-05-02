<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las columnas hora_apertura y hora_cierre son LEGACY — fueron reemplazadas
     * por el campo JSON `horarios` que soporta horario distinto por día. Pero
     * existen como NOT NULL sin default, lo que rompe inserts nuevos.
     *
     * Solución: hacerlas nullable.
     */
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->time('hora_apertura')->nullable()->change();
            $table->time('hora_cierre')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->time('hora_apertura')->nullable(false)->change();
            $table->time('hora_cierre')->nullable(false)->change();
        });
    }
};
