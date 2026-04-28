<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El unique original era (telefono_normalizado) lo que impedia que el
     * MISMO teléfono existiera en clientes de tenants distintos.
     * Cambiar a unique compuesto (tenant_id, telefono_normalizado) permite
     * que cada tenant tenga su propia base de clientes con telefonos
     * que pueden coincidir entre tenants.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Drop el unique viejo (single column). El nombre exacto puede variar
            // según se haya creado, asi que probamos los nombres comunes.
            try {
                $table->dropUnique('clientes_telefono_normalizado_unique');
            } catch (\Throwable $e) { /* puede que no exista con ese nombre */ }
            try {
                $table->dropUnique(['telefono_normalizado']);
            } catch (\Throwable $e) { /* idem */ }
        });

        // Crear el unique compuesto. Si tenant_id es NULL en algunas filas
        // legacy, MySQL trata NULL como distinto a NULL en UNIQUE — no choca.
        Schema::table('clientes', function (Blueprint $table) {
            $table->unique(['tenant_id', 'telefono_normalizado'], 'clientes_tenant_telefono_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            try {
                $table->dropUnique('clientes_tenant_telefono_unique');
            } catch (\Throwable $e) {}
            $table->unique('telefono_normalizado', 'clientes_telefono_normalizado_unique');
        });
    }
};
