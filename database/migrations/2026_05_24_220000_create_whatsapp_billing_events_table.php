<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 💰 Eventos de billing de Meta WhatsApp.
 *
 * Cada vez que Meta nos envía un webhook de status (sent/delivered/read/failed)
 * incluye en `pricing` la información de cuánto cuesta esa conversación.
 *
 * Persistimos solo cuando llega billable=true y conversation.id presente,
 * para no duplicar (status 'sent' y 'delivered' a veces traen el pricing).
 *
 * - conversation_id: identificador único de la conversación de 24h (Meta lo da)
 * - categoria: service|utility|marketing|authentication|referral_conversion
 * - cost_usd: precio según país. Para CO oscila entre $0.0080 y $0.0265
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_billing_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('conversation_id', 191)->index();
            $t->string('message_id', 191)->nullable();
            $t->string('telefono', 25)->nullable()->index();

            $t->string('categoria', 32)->index();     // service|utility|marketing|authentication
            $t->string('pricing_model', 32)->nullable(); // CBP|PMP
            $t->boolean('billable')->default(true);

            $t->decimal('cost_usd', 10, 6)->default(0);
            $t->string('moneda', 8)->default('USD');

            $t->string('origin_type', 32)->nullable(); // marketing|utility|service|authentication
            $t->json('raw_payload')->nullable();

            $t->timestamp('ocurrido_at')->nullable()->index();
            $t->timestamps();

            // Una conversación se factura UNA vez aunque venga varias veces en webhooks
            $t->unique(['tenant_id', 'conversation_id'], 'wba_billing_conv_unique');
            $t->index(['tenant_id', 'categoria', 'ocurrido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_billing_events');
    }
};
