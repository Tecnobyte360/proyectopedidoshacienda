<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'erp_integracion_id')) {
                $table->unsignedBigInteger('erp_integracion_id')->nullable()->after('tenant_id')
                    ->comment('Si el producto vino de SGI, FK a integraciones.id');
            }
            if (!Schema::hasColumn('productos', 'erp_sincronizado_at')) {
                $table->timestamp('erp_sincronizado_at')->nullable()->after('erp_integracion_id')
                    ->comment('Última fecha de sync desde el ERP');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            foreach (['erp_integracion_id', 'erp_sincronizado_at'] as $c) {
                if (Schema::hasColumn('productos', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
