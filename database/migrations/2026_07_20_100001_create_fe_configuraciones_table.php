<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración fiscal + DIAN por TENANT (el emisor ES el tenant). 1:1 con
 * tenants: cada empresa que habilitas con el paquete de facturación electrónica
 * tiene aquí sus credenciales DIAN y su identidad tributaria.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fe_configuraciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

            // ── Identidad tributaria (no vive en tenants) ──────────
            $t->string('nit', 20)->index();
            $t->string('dv', 2)->nullable();
            $t->string('razon_social', 200);
            $t->string('nombre_comercial', 200)->nullable();
            $t->string('tipo_persona', 20)->default('juridica'); // juridica | natural
            $t->string('regimen', 40)->nullable();
            $t->json('responsabilidades_fiscales')->nullable();  // ej. ["O-13","O-15"]
            $t->string('tipo_documento_id', 5)->default('31');   // 31 = NIT

            // ── Ubicación / contacto ───────────────────────────────
            $t->string('municipio_codigo', 10)->nullable();      // DANE
            $t->string('municipio_nombre', 120)->nullable();
            $t->string('departamento_codigo', 10)->nullable();
            $t->string('direccion', 250)->nullable();
            $t->string('telefono', 40)->nullable();
            $t->string('email', 150)->nullable();

            // ── Registro del software ante la DIAN ─────────────────
            $t->string('software_id', 100)->nullable();
            $t->text('software_pin')->nullable();                 // encrypted (cast)
            $t->string('test_set_id', 100)->nullable();

            // ── Certificado de firma (.p12/.pfx) — nunca en git ────
            $t->string('certificado_path', 255)->nullable();
            $t->text('certificado_password')->nullable();         // encrypted (cast)
            $t->timestamp('certificado_vence_at')->nullable();

            // ── Ambiente / estado ──────────────────────────────────
            $t->string('ambiente', 20)->default('habilitacion');  // habilitacion | produccion
            $t->boolean('activa')->default(false);

            // ── Credencial para que SOFTWARE externo consuma la API
            //    de facturación EN NOMBRE de este tenant/emisor ─────
            $t->string('api_key', 80)->nullable()->unique();

            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('fe_configuraciones'); }
};
