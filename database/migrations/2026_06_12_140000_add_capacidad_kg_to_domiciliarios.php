<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domiciliarios', function (Blueprint $t) {
            // ⚖️ Capacidad de carga que puede transportar el domiciliario (kg)
            $t->decimal('capacidad_kg', 8, 2)->nullable()->after('vehiculo');
        });
    }

    public function down(): void
    {
        Schema::table('domiciliarios', function (Blueprint $t) {
            $t->dropColumn('capacidad_kg');
        });
    }
};
