<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_whatsapp_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();

            // Credenciales Meta Cloud API
            $table->string('phone_number_id', 64);    // ej: 123456789012345
            $table->string('waba_id', 64)->nullable(); // WhatsApp Business Account ID
            $table->text('access_token');             // Bearer (System User token de Meta)
            $table->string('api_version', 16)->default('v20.0');

            // Webhook
            $table->string('verify_token', 128);      // que ponemos en Meta App Settings
            $table->text('app_secret')->nullable();   // para validar X-Hub-Signature-256

            // Activacion / preferencias
            $table->boolean('activo')->default(false);
            $table->string('default_lang', 8)->default('es');
            $table->string('display_name')->nullable(); // nombre amigable para UI

            $table->timestamps();

            // Un tenant solo puede tener un phone_number_id (1:1 logico)
            $table->unique(['tenant_id', 'phone_number_id']);

            // Lookup por phone_number_id en webhook entrante (multi-tenant routing)
            $table->index('phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_configs');
    }
};
