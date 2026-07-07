<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración del Asistente IA + SAP POR TENANT:
 *   - Conexión propia al Service Layer de cada cliente (dinámica).
 *   - Agentes/planes activos para ese tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_tenant_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->boolean('activo')->default(true);

            // Conexión Service Layer (por tenant)
            $table->string('sl_mode', 20)->default('direct'); // direct | bridge
            $table->string('sl_url')->nullable();
            $table->string('sl_company')->nullable();
            $table->string('sl_username')->nullable();
            $table->text('sl_password')->nullable();          // encriptado
            $table->unsignedSmallInteger('sl_timeout')->default(30);

            // Modo puente (agente en la red autorizada del cliente)
            $table->string('bridge_url')->nullable();
            $table->text('bridge_token')->nullable();          // encriptado

            // Agentes activos (claves del catálogo config('sap.agentes'))
            $table->json('agentes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_tenant_configs');
    }
};
