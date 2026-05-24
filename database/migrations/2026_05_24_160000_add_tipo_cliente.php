<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'tipo_cliente')) {
                $table->enum('tipo_cliente', ['mayor', 'hogar', 'restaurante'])->nullable();
            }
        });

        Schema::table('conversacion_pedido_estado', function (Blueprint $table) {
            if (!Schema::hasColumn('conversacion_pedido_estado', 'tipo_cliente')) {
                $table->enum('tipo_cliente', ['mayor', 'hogar', 'restaurante'])->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'tipo_cliente')) {
                $table->dropColumn('tipo_cliente');
            }
        });
        Schema::table('conversacion_pedido_estado', function (Blueprint $table) {
            if (Schema::hasColumn('conversacion_pedido_estado', 'tipo_cliente')) {
                $table->dropColumn('tipo_cliente');
            }
        });
    }
};
