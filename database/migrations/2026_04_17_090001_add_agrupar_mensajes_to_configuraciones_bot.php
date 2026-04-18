<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->boolean('agrupar_mensajes_activo')->default(true)->after('saludar_con_promociones');
            $table->unsignedSmallInteger('agrupar_mensajes_segundos')->default(5)->after('agrupar_mensajes_activo');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn(['agrupar_mensajes_activo', 'agrupar_mensajes_segundos']);
        });
    }
};
