<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandera por tenant: ¿el bot ofrece RECOGER EN SEDE como opción?
 * Si es true, el bot pregunta al inicio "¿recoges en sede o domicilio?".
 * Default false → tenants existentes (La Hacienda) no cambian su flujo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            $t->boolean('bot_ofrece_recoger')->default(false)->after('auto_asignar_domiciliario');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $t) {
            $t->dropColumn('bot_ofrece_recoger');
        });
    }
};
