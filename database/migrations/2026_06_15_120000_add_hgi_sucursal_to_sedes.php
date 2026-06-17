<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (!Schema::hasColumn('sedes', 'hgi_sucursal')) {
                // Código StrSucursal de HGI para esta sede (se envía en el export del pedido).
                $table->string('hgi_sucursal')->nullable()->after('meta_phone_number_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (Schema::hasColumn('sedes', 'hgi_sucursal')) {
                $table->dropColumn('hgi_sucursal');
            }
        });
    }
};
