<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🎯 ESTADO ESTRUCTURADO DEL PEDIDO POR CONVERSACIÓN
 *
 * Persiste los datos del pedido EN BD, no en el chat ni en cache.
 * Así el bot nunca "olvida" datos entre mensajes y siempre tiene
 * la verdad estructurada para invocar confirmar_pedido.
 *
 * Una fila por conversación activa. Se resetea cuando:
 *  - Se confirma el pedido (paso_actual = 'confirmado')
 *  - El cliente saluda tras N horas de inactividad (auto_reset)
 *  - El admin lo limpia manualmente desde /chat
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversacion_pedido_estado', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversacion_id')->unique()->constrained('conversaciones_whatsapp')->cascadeOnDelete();

            // Paso actual del flujo
            // inicio | producto | entrega | identificacion | confirmacion | confirmado | abandonado
            $table->string('paso_actual', 30)->default('inicio')->index();

            // 🛒 Productos seleccionados (array de {name, quantity, unit, price?})
            $table->json('productos')->nullable();

            // 🚚 Entrega
            $table->string('metodo_entrega', 20)->nullable()->comment('domicilio | recoger');
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->string('direccion', 300)->nullable();
            $table->string('barrio', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->boolean('cobertura_validada')->default(false);
            $table->decimal('distancia_km', 8, 2)->nullable();
            $table->decimal('costo_envio', 12, 2)->nullable();

            // 👤 Identificación del cliente
            $table->string('cedula', 30)->nullable()->index();
            $table->string('nombre_cliente', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('cliente_existe_erp')->default(false);
            $table->json('datos_erp')->nullable();

            // 💳 Pago / extras
            $table->string('metodo_pago', 30)->nullable();
            $table->string('cupon_code', 50)->nullable();
            $table->text('notas')->nullable();

            // 📊 Validaciones realizadas (para no repetir tools)
            // {producto_buscado: bool, cobertura: bool, cliente_erp: bool, sede_horario: bool}
            $table->json('validaciones')->nullable();

            // 🎯 Resultado final
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('abandonado_at')->nullable();
            $table->string('motivo_abandono', 100)->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'paso_actual']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversacion_pedido_estado');
    }
};
