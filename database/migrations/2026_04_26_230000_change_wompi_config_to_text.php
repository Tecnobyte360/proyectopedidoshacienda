<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cast 'encrypted:array' de Eloquent guarda un blob cifrado de Laravel
     * (Crypt::encrypt) que NO es JSON válido. MySQL rechaza el insert porque
     * la columna se creó con tipo JSON. Cambiamos a TEXT/LONGTEXT para que
     * admita el payload cifrado tal cual.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'wompi_config')) return;

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MODIFY mantiene los datos existentes y solo cambia el tipo
            DB::statement('ALTER TABLE tenants MODIFY COLUMN wompi_config LONGTEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tenants ALTER COLUMN wompi_config TYPE TEXT USING wompi_config::text');
        } else {
            // SQLite: las columnas son tipadas dinámicamente, no requiere cambio
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tenants', 'wompi_config')) return;

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE tenants MODIFY COLUMN wompi_config JSON NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tenants ALTER COLUMN wompi_config TYPE JSONB USING wompi_config::jsonb');
        }
    }
};
