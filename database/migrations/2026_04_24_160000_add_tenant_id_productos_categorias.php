<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega tenant_id a tablas que el modelo Eloquent espera pero la
 * migración vieja se saltó (porque tenían nombres diferentes a los
 * en la lista). Sin esto, las queries con TenantScope tiran error
 * "Unknown column 'tabla.tenant_id'".
 */
return new class extends Migration {
    public function up(): void
    {
        // Mapeo: tabla → modelo (referencia para asignar tenant_id en datos existentes)
        $tablas = [
            'productos_categorias',
            'ans_pedidos',     // por si acaso
        ];

        foreach ($tablas as $tabla) {
            if (!Schema::hasTable($tabla)) continue;
            if (Schema::hasColumn($tabla, 'tenant_id')) continue;

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')
                      ->references('id')->on('tenants')
                      ->nullOnDelete();
                $table->index('tenant_id');
            });

            // Backfill: si solo existe un tenant, asignar todas las filas a ese.
            // En producción multi-tenant este paso requiere logica especifica.
            $primerTenant = DB::table('tenants')
                ->where('activo', true)
                ->orderBy('id')
                ->value('id');

            if ($primerTenant) {
                DB::table($tabla)->whereNull('tenant_id')->update(['tenant_id' => $primerTenant]);
            }
        }
    }

    public function down(): void
    {
        foreach (['productos_categorias', 'ans_pedidos'] as $tabla) {
            if (!Schema::hasTable($tabla)) continue;
            if (!Schema::hasColumn($tabla, 'tenant_id')) continue;

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                try { $table->dropForeign(["{$tabla}_tenant_id_foreign"]); } catch (\Throwable $e) {}
                try { $table->dropIndex(["{$tabla}_tenant_id_index"]); } catch (\Throwable $e) {}
                $table->dropColumn('tenant_id');
            });
        }
    }
};
