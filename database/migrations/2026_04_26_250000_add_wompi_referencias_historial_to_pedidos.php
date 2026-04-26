<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'wompi_referencias_historial')) {
                $table->json('wompi_referencias_historial')->nullable()->after('wompi_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'wompi_referencias_historial')) {
                $table->dropColumn('wompi_referencias_historial');
            }
        });
    }
};
