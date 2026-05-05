<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Toggle por sede: ¿acepta pedidos cuando está cerrada?
        // Si SI → los registra con programado_para = próxima apertura
        Schema::table('sedes', function (Blueprint $table) {
            $table->boolean('aceptar_pedidos_cerrada')->default(false)
                ->after('cobertura_centro_lng')
                ->comment('Si está abierto, acepta pedidos fuera de horario y los programa para la próxima apertura');
        });

        // Campo de pedido programado
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'programado_para')) {
                $table->timestamp('programado_para')->nullable()
                    ->after('estado')
                    ->comment('Si está seteado, este pedido se preparará/despachará a partir de este momento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn('aceptar_pedidos_cerrada');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'programado_para')) {
                $table->dropColumn('programado_para');
            }
        });
    }
};
