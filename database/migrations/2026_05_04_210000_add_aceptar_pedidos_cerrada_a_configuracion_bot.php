<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toggle global por tenant: ¿el bot acepta pedidos fuera de horario?
        // Si está activo, los pedidos que entran cuando todas las sedes están
        // cerradas se registran como "programados" para la próxima apertura.
        Schema::table('configuracion_bot', function (Blueprint $table) {
            $table->boolean('aceptar_pedidos_fuera_horario')->default(false)
                ->after('enviar_link_pago')
                ->comment('Si está activo, acepta pedidos cuando la sede está cerrada y los programa para la próxima apertura');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_bot', function (Blueprint $table) {
            $table->dropColumn('aceptar_pedidos_fuera_horario');
        });
    }
};
