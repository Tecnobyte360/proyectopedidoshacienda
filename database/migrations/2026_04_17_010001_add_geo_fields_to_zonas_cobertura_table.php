<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas_cobertura', function (Blueprint $table) {
            $table->json('poligono')->nullable()->after('color');
            $table->decimal('centro_lat', 10, 7)->nullable()->after('poligono');
            $table->decimal('centro_lng', 10, 7)->nullable()->after('centro_lat');
            $table->decimal('area_km2', 10, 4)->nullable()->after('centro_lng');
        });
    }

    public function down(): void
    {
        Schema::table('zonas_cobertura', function (Blueprint $table) {
            $table->dropColumn(['poligono', 'centro_lat', 'centro_lng', 'area_km2']);
        });
    }
};
