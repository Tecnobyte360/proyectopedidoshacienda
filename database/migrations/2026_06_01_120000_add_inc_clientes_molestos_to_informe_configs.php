<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_informe_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_informe_configs', 'inc_clientes_molestos')) {
                $table->boolean('inc_clientes_molestos')->default(true)->after('inc_palabras_top');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_informe_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_informe_configs', 'inc_clientes_molestos')) {
                $table->dropColumn('inc_clientes_molestos');
            }
        });
    }
};
