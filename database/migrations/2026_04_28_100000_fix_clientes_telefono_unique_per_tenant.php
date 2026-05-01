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
        $database = DB::connection()->getDatabaseName();

        // Detectar todos los indices UNIQUE que existan sobre la columna
        // telefono_normalizado y dropearlos uno por uno con SQL crudo.
        $indices = DB::select(
            "SELECT DISTINCT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = 'clientes'
               AND COLUMN_NAME  = 'telefono_normalizado'
               AND NON_UNIQUE   = 0
               AND INDEX_NAME  != 'PRIMARY'",
            [$database]
        );

        foreach ($indices as $idx) {
            $name = $idx->INDEX_NAME;
            // Saltar el indice compuesto si ya existe (re-ejecuciones)
            if ($name === 'clientes_tenant_telefono_unique') continue;
            try {
                DB::statement("ALTER TABLE `clientes` DROP INDEX `{$name}`");
            } catch (\Throwable $e) { /* otra migracion ya lo dropeo */ }
        }

        // Crear el unique compuesto solo si no existe
        $existeCompuesto = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'clientes'
               AND INDEX_NAME = 'clientes_tenant_telefono_unique'",
            [$database]
        );

        if (!$existeCompuesto || (int) $existeCompuesto->c === 0) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique(['tenant_id', 'telefono_normalizado'], 'clientes_tenant_telefono_unique');
            });
        }
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
