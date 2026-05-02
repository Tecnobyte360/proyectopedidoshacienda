<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega configuración de Google Maps por tenant.
     * Cada tenant puede tener su propia API Key (paga su cuota), centro
     * por defecto del mapa (lat/lng/zoom) y toggle de habilitado.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('google_maps_api_key')->nullable()->after('descripcion_negocio');
            $table->boolean('google_maps_activo')->default(false)->after('google_maps_api_key');
            $table->decimal('google_maps_centro_lat', 10, 7)->nullable()->after('google_maps_activo');
            $table->decimal('google_maps_centro_lng', 10, 7)->nullable()->after('google_maps_centro_lat');
            $table->unsignedTinyInteger('google_maps_zoom')->default(13)->after('google_maps_centro_lng');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'google_maps_api_key',
                'google_maps_activo',
                'google_maps_centro_lat',
                'google_maps_centro_lng',
                'google_maps_zoom',
            ]);
        });
    }
};
