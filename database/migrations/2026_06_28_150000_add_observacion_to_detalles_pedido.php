<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            if (!Schema::hasColumn('detalles_pedido', 'observacion')) {
                $table->string('observacion', 500)->nullable()->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            if (Schema::hasColumn('detalles_pedido', 'observacion')) {
                $table->dropColumn('observacion');
            }
        });
    }
};
