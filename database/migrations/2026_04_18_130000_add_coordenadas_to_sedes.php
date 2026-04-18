<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (!Schema::hasColumn('sedes', 'latitud')) {
                $table->double('latitud')->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('sedes', 'longitud')) {
                $table->double('longitud')->nullable()->after('latitud');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
