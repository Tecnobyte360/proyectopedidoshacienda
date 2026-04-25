<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campanas_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('nombre', 150);
            $table->text('mensaje');                                         // texto con placeholders {nombre}
            $table->string('media_url', 500)->nullable();                    // imagen/audio opcional

            // Audiencia (filtros)
            $table->string('audiencia_tipo', 30)->default('todos');          // todos | zona | sede | con_pedidos | sin_pedidos | manual
            $table->json('audiencia_filtros')->nullable();                   // {zona_id, sede_id, lista_telefonos, ...}

            // Throttle anti-baneo
            $table->integer('intervalo_min_seg')->default(8);                // mínimo entre mensajes
            $table->integer('intervalo_max_seg')->default(20);               // máximo (jitter aleatorio)
            $table->integer('lote_tamano')->default(50);                    // mensajes por lote
            $table->integer('descanso_lote_min')->default(30);              // descanso después de cada lote (min)
            $table->time('ventana_desde')->default('08:00:00');             // solo enviar entre estas horas
            $table->time('ventana_hasta')->default('20:00:00');

            // Programación / estado
            $table->string('estado', 20)->default('borrador');               // borrador | programada | corriendo | pausada | completada | cancelada
            $table->timestamp('programada_para')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('completada_at')->nullable();

            // Conexión WA a usar (id en TecnoByteApp)
            $table->unsignedBigInteger('connection_id')->nullable();

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();

            // Métricas
            $table->integer('total_destinatarios')->default(0);
            $table->integer('total_enviados')->default(0);
            $table->integer('total_fallidos')->default(0);
            $table->integer('total_pendientes')->default(0);

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });

        Schema::create('campana_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('campanas_whatsapp')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            $table->string('nombre', 150)->nullable();
            $table->string('telefono', 30);

            $table->string('estado', 20)->default('pendiente');             // pendiente | enviado | fallido | omitido
            $table->text('mensaje_renderizado')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->text('error_detalle')->nullable();
            $table->integer('intentos')->default(0);

            $table->timestamps();

            $table->index(['campana_id', 'estado']);
            $table->unique(['campana_id', 'telefono'], 'uniq_camp_tel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campana_destinatarios');
        Schema::dropIfExists('campanas_whatsapp');
    }
};
