<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 💳 Credenciales Wompi del dueño Kivox (TecnoByte360) en la tabla singleton
 * de configuración. Permite configurarlas desde la UI en vez de tocar .env.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_modo')) {
                $t->string('saas_wompi_modo', 16)->default('sandbox')->after('whatsapp_api_base_url'); // sandbox|produccion
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_public_key')) {
                $t->string('saas_wompi_public_key', 200)->nullable()->after('saas_wompi_modo');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_private_key')) {
                $t->string('saas_wompi_private_key', 200)->nullable()->after('saas_wompi_public_key');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_integrity_secret')) {
                $t->string('saas_wompi_integrity_secret', 200)->nullable()->after('saas_wompi_private_key');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_events_secret')) {
                $t->string('saas_wompi_events_secret', 200)->nullable()->after('saas_wompi_integrity_secret');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'saas_wompi_redirect_url')) {
                $t->string('saas_wompi_redirect_url', 255)->nullable()->after('saas_wompi_events_secret');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $t) {
            foreach (['saas_wompi_modo','saas_wompi_public_key','saas_wompi_private_key','saas_wompi_integrity_secret','saas_wompi_events_secret','saas_wompi_redirect_url'] as $c) {
                if (Schema::hasColumn('configuracion_plataforma', $c)) $t->dropColumn($c);
            }
        });
    }
};
