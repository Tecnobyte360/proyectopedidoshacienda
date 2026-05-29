<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            // 👍 Reacciones de emoji al mensaje.
            //
            // - reaccion_operador: emoji que el operador puso al mensaje del cliente
            //   (visible para el cliente en su WhatsApp y para todo el equipo).
            // - reaccion_cliente: emoji que el cliente puso a un mensaje del bot/operador.
            //
            // Ambas son strings de hasta 16 chars (algunos emojis son multibyte
            // compuestos, ej. 👨‍👩‍👧‍👦). NULL si no hay reacción.
            $table->string('reaccion_operador', 16)->nullable()->after('meta');
            $table->dateTime('reaccion_operador_at')->nullable()->after('reaccion_operador');

            $table->string('reaccion_cliente', 16)->nullable()->after('reaccion_operador_at');
            $table->dateTime('reaccion_cliente_at')->nullable()->after('reaccion_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            $table->dropColumn([
                'reaccion_operador', 'reaccion_operador_at',
                'reaccion_cliente',  'reaccion_cliente_at',
            ]);
        });
    }
};
