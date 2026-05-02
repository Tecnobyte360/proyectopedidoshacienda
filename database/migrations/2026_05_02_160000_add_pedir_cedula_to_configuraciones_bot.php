<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configura si el bot debe solicitar la cédula del cliente al inicio
     * de la conversación, y opcionalmente con qué consulta validar/buscar
     * el cliente en el ERP.
     */
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->boolean('pedir_cedula')->default(false)->after('bot_modo_agente');
            $table->boolean('cedula_obligatoria')->default(false)->after('pedir_cedula');
            $table->string('cedula_descripcion', 300)->nullable()->after('cedula_obligatoria');
            $table->unsignedBigInteger('cedula_consulta_id')->nullable()->after('cedula_descripcion');

            $table->foreign('cedula_consulta_id')
                  ->references('id')->on('integracion_consultas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropForeign(['cedula_consulta_id']);
            $table->dropColumn(['pedir_cedula', 'cedula_obligatoria', 'cedula_descripcion', 'cedula_consulta_id']);
        });
    }
};
