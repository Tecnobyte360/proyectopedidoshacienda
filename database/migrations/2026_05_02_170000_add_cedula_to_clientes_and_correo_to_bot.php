<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Agrega columna 'cedula' a clientes (para facturacion electronica).
     * 2. Agrega 'pedir_correo' a configuraciones_bot — el bot puede pedir
     *    cedula + correo a clientes nuevos para futura facturacion.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'cedula')) {
                $table->string('cedula', 30)->nullable()->after('email')->index();
            }
        });

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->boolean('pedir_correo')->default(false)->after('cedula_consulta_id');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'cedula')) {
                $table->dropColumn('cedula');
            }
        });
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('pedir_correo');
        });
    }
};
