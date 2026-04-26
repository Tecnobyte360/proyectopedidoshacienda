<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('encuestas_pedido')) {
            Schema::create('encuestas_pedido', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('pedido_id');
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('domiciliario_id')->nullable();

                // Calificaciones (1-5 estrellas)
                $table->tinyInteger('calificacion_proceso')->nullable();
                $table->tinyInteger('calificacion_domiciliario')->nullable();
                $table->text('comentario_proceso')->nullable();
                $table->text('comentario_domiciliario')->nullable();

                $table->boolean('recomendaria')->nullable();   // ¿Recomendarías?
                $table->timestamp('enviada_at')->nullable();   // cuando se envió WA
                $table->timestamp('completada_at')->nullable();// cuando respondió
                $table->timestamp('vista_at')->nullable();     // cuando abrió el link

                $table->timestamps();

                $table->index(['tenant_id', 'completada_at']);
                $table->index(['domiciliario_id', 'calificacion_domiciliario']);
                $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
            });
        }

        // Configuración por tenant
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'encuesta_activa')) {
                $table->boolean('encuesta_activa')->default(true)->after('cumpleanos_dias_vigencia_beneficio');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'encuesta_delay_minutos')) {
                $table->integer('encuesta_delay_minutos')->default(15)->after('encuesta_activa');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'encuesta_mensaje')) {
                $table->text('encuesta_mensaje')->nullable()->after('encuesta_delay_minutos');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_pedido');

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            foreach (['encuesta_activa', 'encuesta_delay_minutos', 'encuesta_mensaje'] as $c) {
                if (Schema::hasColumn('configuraciones_bot', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
