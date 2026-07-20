<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica como FEATURE del plan, siguiendo el patrón existente
 * (feature_whatsapp, feature_ia, feature_reportes, feature_api...). Un tenant
 * tiene el módulo habilitado si su plan lo incluye.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            if (!Schema::hasColumn('planes', 'feature_facturacion_electronica')) {
                $t->boolean('feature_facturacion_electronica')->default(false)->after('feature_api');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            if (Schema::hasColumn('planes', 'feature_facturacion_electronica')) {
                $t->dropColumn('feature_facturacion_electronica');
            }
        });
    }
};
