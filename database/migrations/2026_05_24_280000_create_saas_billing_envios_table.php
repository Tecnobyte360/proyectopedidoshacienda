<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 📤 Log de envíos de notificaciones SaaS Billing por WhatsApp.
 *
 * Registra cada intento de envío (factura nueva, recordatorios escalonados,
 * notificación de suspensión) para auditoría y monitoreo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('saas_billing_envios', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('pago_id')->nullable()->index();
            $t->unsignedBigInteger('suscripcion_id')->nullable()->index();

            $t->string('tipo', 32)->index();   // factura | recordatorio | suspendido
            $t->string('etapa', 32)->nullable(); // preaviso | vence_hoy | vencio_ayer | urgencia | suspendido | factura
            $t->string('canal', 16)->default('whatsapp'); // whatsapp | email | sms
            $t->string('telefono', 25)->nullable();

            $t->decimal('monto', 12, 2)->nullable();
            $t->string('moneda', 8)->nullable();

            $t->boolean('ok')->default(false)->index();
            $t->text('mensaje')->nullable();
            $t->text('link_pago')->nullable();
            $t->string('error', 500)->nullable();

            $t->timestamps();

            $t->index(['tenant_id', 'created_at']);
            $t->index(['tipo', 'ok']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_envios');
    }
};
