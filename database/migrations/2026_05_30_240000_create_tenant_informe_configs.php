<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_informe_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $t->boolean('activo')->default(false);
            $t->enum('frecuencia', ['diario', 'semanal', 'mensual'])->default('semanal');
            $t->unsignedTinyInteger('dia_semana')->nullable()->comment('1=lunes ... 7=domingo (solo semanal)');
            $t->unsignedTinyInteger('dia_mes')->nullable()->comment('1-28 (solo mensual)');
            $t->time('hora_envio')->default('08:00:00');

            // Destinatarios
            $t->json('emails')->nullable()->comment('array de emails');
            $t->json('telefonos_whatsapp')->nullable()->comment('array de tel E.164 para enviar por WA');

            // Métricas a incluir (toggle cada una)
            $t->boolean('inc_volumen')->default(true);
            $t->boolean('inc_horas_pico')->default(true);
            $t->boolean('inc_tiempo_respuesta')->default(true);
            $t->boolean('inc_reacciones')->default(true);
            $t->boolean('inc_top_clientes')->default(true);
            $t->boolean('inc_sin_responder')->default(true);
            $t->boolean('inc_palabras_top')->default(false);

            $t->timestamp('ultimo_envio_at')->nullable();
            $t->timestamps();

            $t->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_informe_configs');
    }
};
