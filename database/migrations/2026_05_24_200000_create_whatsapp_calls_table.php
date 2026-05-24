<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 📞 Registro de llamadas WhatsApp Business Calling API (Meta).
 *
 * - direccion: outbound (operador → cliente) o inbound (cliente → operador)
 * - estado: requested, ringing, connecting, connected, ended, failed, rejected, no_permission
 * - call_id: id que asigna Meta (wamid de la llamada)
 * - sdp_offer / sdp_answer: signaling WebRTC (truncado a 65535)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_calls', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('conversacion_id')->nullable()->index();
            $t->unsignedBigInteger('operador_user_id')->nullable()->index();
            $t->unsignedBigInteger('cliente_id')->nullable()->index();

            $t->string('telefono', 25)->index();
            $t->enum('direccion', ['outbound', 'inbound']);
            $t->string('call_id', 191)->nullable()->unique();
            $t->string('phone_number_id', 64)->nullable();

            $t->string('estado', 32)->default('requested')->index();
            $t->string('motivo_termino', 64)->nullable();

            // signaling WebRTC
            $t->text('sdp_offer')->nullable();
            $t->text('sdp_answer')->nullable();

            // tiempos
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('ringing_at')->nullable();
            $t->timestamp('connected_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('duracion_seg')->default(0);

            // costo
            $t->decimal('costo_usd', 10, 6)->default(0);
            $t->string('moneda', 8)->nullable();

            $t->json('meta_payload')->nullable(); // último webhook completo
            $t->text('error_msg')->nullable();

            $t->timestamps();

            $t->index(['tenant_id', 'estado']);
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_calls');
    }
};
