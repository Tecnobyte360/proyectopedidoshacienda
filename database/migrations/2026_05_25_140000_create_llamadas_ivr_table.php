<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llamadas_ivr', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->string('asterisk_uniqueid', 60)->unique();
            $t->string('caller_id', 30)->index();
            $t->string('telefono_normalizado', 20)->index();
            $t->string('did_destino', 30)->nullable();          // número Twilio que recibió
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            $t->string('estado', 30)->default('en_curso')->index();
            // en_curso | terminada_ok | terminada_timeout | terminada_invalido
            // transferida_ok | transferida_no_contesto | voicemail

            $t->string('opcion_elegida', 30)->nullable();       // "1" "2" "3" o "estado_pedido"
            $t->foreignId('pedido_consultado_id')->nullable()->constrained('pedidos')->nullOnDelete();

            $t->boolean('transferida')->default(false);
            $t->string('transferida_a', 50)->nullable();        // operador, número externo
            $t->boolean('asesor_contesto')->default(false);
            $t->boolean('dejo_voicemail')->default(false);
            $t->string('voicemail_path')->nullable();

            $t->integer('duracion_segundos')->nullable();
            $t->timestamp('iniciada_at')->index();
            $t->timestamp('terminada_at')->nullable();

            // Eventos (JSON con histórico de qué pasó en la llamada)
            $t->json('eventos')->nullable();

            $t->timestamps();

            $t->index(['tenant_id', 'iniciada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llamadas_ivr');
    }
};
