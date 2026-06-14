<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'numeros_prueba')) {
                // Números (coma-separados) que el bot atiende AUNQUE esté apagado (modo prueba).
                $table->text('numeros_prueba')->nullable()->after('activo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'numeros_prueba')) {
                $table->dropColumn('numeros_prueba');
            }
        });
    }
};
