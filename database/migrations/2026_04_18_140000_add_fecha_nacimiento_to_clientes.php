<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('email');
            }

            // Para evitar enviar varias felicitaciones el mismo año si el cron
            // corre múltiples veces o hay reinicios. Se almacena el año de la
            // última vez que se envió.
            if (!Schema::hasColumn('clientes', 'ultima_felicitacion_anio')) {
                $table->unsignedSmallInteger('ultima_felicitacion_anio')->nullable()->after('fecha_nacimiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['fecha_nacimiento', 'ultima_felicitacion_anio']);
        });
    }
};
