<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('slug', 80)->unique();             // la-hacienda → subdominio futuro
            $table->string('logo_url')->nullable();

            // Plan / facturación
            $table->string('plan', 30)->default('basico');     // basico | pro | empresa
            $table->boolean('activo')->default(true);
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable();

            // Contacto
            $table->string('contacto_nombre', 120)->nullable();
            $table->string('contacto_email', 150)->nullable();
            $table->string('contacto_telefono', 30)->nullable();

            // Branding
            $table->string('color_primario', 10)->default('#d68643');
            $table->string('color_secundario', 10)->default('#a85f24');

            // Credenciales sensibles del tenant (cifradas idealmente)
            $table->text('openai_api_key')->nullable();
            $table->json('whatsapp_config')->nullable();        // email, password, urls API si difieren

            // Notas internas (super-admin)
            $table->text('notas_internas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
