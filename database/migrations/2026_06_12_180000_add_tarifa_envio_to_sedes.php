<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $t) {
            // 🚚 Tarifa de envío por distancia: base + (km * por_km)
            $t->decimal('tarifa_envio_base', 10, 2)->default(3000)->after('cobertura_costo_envio');
            $t->decimal('tarifa_envio_km', 10, 2)->default(1500)->after('tarifa_envio_base');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $t) {
            $t->dropColumn(['tarifa_envio_base', 'tarifa_envio_km']);
        });
    }
};
