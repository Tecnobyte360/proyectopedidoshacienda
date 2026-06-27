<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'foto_entrega')) {
                $table->string('foto_entrega')->nullable()->after('token_entrega');
            }
            if (!Schema::hasColumn('pedidos', 'motivo_no_entrega')) {
                $table->string('motivo_no_entrega')->nullable()->after('foto_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'foto_entrega')) $table->dropColumn('foto_entrega');
            if (Schema::hasColumn('pedidos', 'motivo_no_entrega')) $table->dropColumn('motivo_no_entrega');
        });
    }
};
