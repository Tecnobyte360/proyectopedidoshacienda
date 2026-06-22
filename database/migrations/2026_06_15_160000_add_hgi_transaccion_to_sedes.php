<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (!Schema::hasColumn('sedes', 'hgi_transaccion')) {
                // Código IntTransaccion de HGI para pedidos de esta sede.
                $table->string('hgi_transaccion')->nullable()->after('hgi_sucursal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (Schema::hasColumn('sedes', 'hgi_transaccion')) {
                $table->dropColumn('hgi_transaccion');
            }
        });
    }
};
