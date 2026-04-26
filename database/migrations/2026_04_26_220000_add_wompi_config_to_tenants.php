<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'wompi_config')) {
                $table->json('wompi_config')->nullable()->after('whatsapp_config');
            }
            if (!Schema::hasColumn('tenants', 'wompi_modo')) {
                // 'sandbox' o 'produccion'
                $table->string('wompi_modo', 20)->default('sandbox')->after('wompi_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['wompi_config', 'wompi_modo'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
