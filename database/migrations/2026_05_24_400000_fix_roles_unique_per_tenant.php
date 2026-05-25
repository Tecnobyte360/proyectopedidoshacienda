<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 🔧 Permitir que cada tenant tenga su propio rol con el mismo nombre.
 *
 * Spatie por defecto pone UNIQUE (name, guard_name). Como ahora aislamos
 * roles por tenant, el constraint debe ser UNIQUE (name, guard_name, tenant_id).
 * Así dos tenants pueden tener cada uno su rol 'admin' sin colisionar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Detectar y eliminar el índice antiguo
        $indices = collect(DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'"));
        if ($indices->isNotEmpty()) {
            Schema::table('roles', function (Blueprint $t) {
                $t->dropUnique('roles_name_guard_name_unique');
            });
        }

        // Crear nuevo índice que incluye tenant_id
        // En MySQL, una columna NULL en un UNIQUE puede repetirse, así que
        // los roles globales (tenant_id=null) seguirán con unicidad por nombre.
        // Pero como ahora cada tenant tendrá su propia copia, lo aceptamos.
        Schema::table('roles', function (Blueprint $t) {
            $t->unique(['name', 'guard_name', 'tenant_id'], 'roles_name_guard_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $t) {
            $t->dropUnique('roles_name_guard_tenant_unique');
        });
        Schema::table('roles', function (Blueprint $t) {
            $t->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
