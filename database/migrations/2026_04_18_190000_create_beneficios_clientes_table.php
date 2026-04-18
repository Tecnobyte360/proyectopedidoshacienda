<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficios_clientes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete();

            // Si vino de una felicitación de cumpleaños, lo linkeamos
            $table->foreignId('felicitacion_id')
                ->nullable()
                ->constrained('felicitaciones_cumpleanos')
                ->nullOnDelete();

            // envio_gratis | descuento_pct | descuento_monto
            $table->string('tipo', 30)->index();

            // Para descuentos: porcentaje o monto
            $table->decimal('valor', 10, 2)->nullable();

            $table->string('origen', 30)->default('cumpleanos');  // cumpleanos | manual | promo
            $table->string('descripcion', 200)->nullable();

            $table->timestamp('otorgado_at')->useCurrent();
            $table->date('vigente_hasta');

            // Control de uso
            $table->timestamp('usado_at')->nullable();
            $table->foreignId('pedido_id')
                ->nullable()
                ->constrained('pedidos')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['cliente_id', 'usado_at', 'vigente_hasta']);
        });

        // También agregamos días de vigencia configurable al bot
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_dias_vigencia_beneficio')) {
                $table->unsignedTinyInteger('cumpleanos_dias_vigencia_beneficio')->default(3);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficios_clientes');

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('cumpleanos_dias_vigencia_beneficio');
        });
    }
};
