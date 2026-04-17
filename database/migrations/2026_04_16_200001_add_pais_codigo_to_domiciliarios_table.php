<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domiciliarios', function (Blueprint $table) {
            $table->string('pais_codigo', 6)->default('+57')->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('domiciliarios', function (Blueprint $table) {
            $table->dropColumn('pais_codigo');
        });
    }
};
