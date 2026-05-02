<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API Key separada para uso SERVER-SIDE (sin restricción HTTP referrer).
     * La frontend key (google_maps_api_key) tiene restricción de referrer y
     * no puede usarse desde PHP. Esta segunda key se usa para el geocoding
     * que ejecuta el bot al validar cobertura.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('google_maps_server_api_key')->nullable()->after('google_maps_zoom');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('google_maps_server_api_key');
        });
    }
};
