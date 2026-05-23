<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔄 ERP RETRY QUEUE
 *
 * Tabla donde se acumulan las sincronizaciones pendientes con ERP cuando
 * el SQL Server / API del ERP están caídos. Un comando artisan corre
 * cada 5 min y reintenta hasta éxito o tope de intentos.
 *
 * Casos típicos que llegan aquí:
 *   - tipo=cliente_crear → ClienteErpService::crear falló (network/down)
 *   - tipo=pedido_export → exportar pedido al ERP falló
 *
 * El cliente NO sufre el outage: su pedido ya quedó en BD local con un
 * número. La sincronización con ERP ocurre cuando el ERP vuelva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_pedidos_pendientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('integracion_id')->nullable();
            $table->unsignedBigInteger('conversacion_id')->nullable();
            $table->unsignedBigInteger('pedido_id')->nullable();  // pedido local YA creado
            $table->string('tipo', 50);  // cliente_crear | pedido_export
            $table->string('telefono', 30)->nullable();
            $table->json('payload');  // datos a enviar al ERP
            $table->string('estado', 30)->default('pendiente');  // pendiente | procesando | completado | fallido_max | descartado
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->unsignedSmallInteger('max_intentos')->default(20);
            $table->text('ultimo_error')->nullable();
            $table->timestamp('ultimo_intento_at')->nullable();
            $table->timestamp('proximo_intento_at')->nullable();  // backoff exponencial
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'proximo_intento_at']);
            $table->index(['tenant_id', 'estado']);
            $table->index(['pedido_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_pedidos_pendientes');
    }
};
