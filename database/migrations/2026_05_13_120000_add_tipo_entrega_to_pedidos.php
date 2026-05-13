<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'tipo_entrega')) {
                // 'domicilio' | 'recoger'
                $table->string('tipo_entrega', 20)->default('domicilio')->after('barrio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'tipo_entrega')) {
                $table->dropColumn('tipo_entrega');
            }
        });
    }
};
