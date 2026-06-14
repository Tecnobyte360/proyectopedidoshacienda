<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'wompi_activo')) {
                // Default true: los tenants que ya usaban Wompi siguen activos.
                $table->boolean('wompi_activo')->default(true)->after('wompi_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'wompi_activo')) {
                $table->dropColumn('wompi_activo');
            }
        });
    }
};
