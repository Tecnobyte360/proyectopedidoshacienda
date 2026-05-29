<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            // 💬 Si este mensaje es respuesta a otro (operador → mensaje del cliente
            // o cliente → mensaje del operador), guardamos el ID local del mensaje
            // referenciado. NULL = mensaje nuevo no asociado.
            $table->unsignedBigInteger('respondiendo_a_mensaje_id')->nullable()->after('mensaje_externo_id');
            $table->index('respondiendo_a_mensaje_id');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->dropIndex(['respondiendo_a_mensaje_id']);
            $table->dropColumn('respondiendo_a_mensaje_id');
        });
    }
};
