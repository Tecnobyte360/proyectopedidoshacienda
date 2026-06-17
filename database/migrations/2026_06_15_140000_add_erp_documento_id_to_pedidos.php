<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'erp_documento_id')) {
                // Número de documento que devuelve el ERP (HGI) al exportar el pedido.
                $table->string('erp_documento_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'erp_documento_id')) {
                $table->dropColumn('erp_documento_id');
            }
        });
    }
};
