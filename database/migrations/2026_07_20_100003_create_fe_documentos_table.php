<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento electrónico (factura / nota crédito / nota débito) por TENANT y su
 * ciclo de vida ante la DIAN. Un documento ACEPTADO no se edita ni se borra:
 * se corrige con una nota que lo referencia.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fe_documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('fe_resolucion_id')->nullable()->constrained('fe_resoluciones')->nullOnDelete();

            // Origen (de qué software/documento vino): pedido KIVOX, ERP externo, API...
            $t->string('origen', 40)->nullable();          // 'erp' | 'kivox_pedido' | 'api'
            $t->string('origen_ref', 100)->nullable();

            $t->string('tipo_documento', 20)->default('factura');
            $t->string('prefijo', 10)->nullable();
            $t->unsignedBigInteger('numero')->nullable();
            $t->string('numero_completo', 30)->nullable()->index();

            // ── Identificadores DIAN ───────────────────────────────
            $t->string('cufe', 200)->nullable()->index();  // CUFE (factura) / CUDE (notas)
            $t->string('zip_key', 200)->nullable();
            $t->string('track_id', 200)->nullable();

            // ── Ciclo de vida ──────────────────────────────────────
            $t->string('estado', 20)->default('draft')->index();
            $t->text('dian_mensaje')->nullable();
            $t->json('dian_errores')->nullable();

            // ── Artefactos ─────────────────────────────────────────
            $t->string('xml_path', 255)->nullable();
            $t->string('pdf_path', 255)->nullable();
            $t->string('attached_document_path', 255)->nullable();

            // ── Auditoría (payloads completos) ─────────────────────
            $t->json('request_payload')->nullable();
            $t->json('response_payload')->nullable();

            // ── Idempotencia (evita doble emisión por reintentos) ──
            $t->string('idempotency_key', 100)->nullable();

            // ── Denormalizado para listados ────────────────────────
            $t->string('cliente_documento', 30)->nullable()->index();
            $t->string('cliente_nombre', 200)->nullable();
            $t->decimal('total', 16, 2)->nullable();
            $t->string('moneda', 5)->default('COP');

            $t->timestamp('sent_at')->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();

            // No repetir número por tenant+tipo, ni reusar idempotency_key.
            $t->unique(['tenant_id', 'tipo_documento', 'prefijo', 'numero'], 'fe_doc_numero_uq');
            $t->unique(['tenant_id', 'idempotency_key'], 'fe_doc_idem_uq');
        });
    }

    public function down(): void { Schema::dropIfExists('fe_documentos'); }
};
