<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolución de numeración DIAN por TENANT y tipo de documento.
 * `numero_actual` se incrementa SIEMPRE con lockForUpdate (NumeradorService)
 * para no saltar ni repetir consecutivos — lo que la DIAN rechaza.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fe_resoluciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $t->string('tipo_documento', 20)->default('factura'); // factura | nota_credito | nota_debito
            $t->string('prefijo', 10)->nullable();
            $t->unsignedBigInteger('numero_desde');
            $t->unsignedBigInteger('numero_hasta');
            $t->unsignedBigInteger('numero_actual')->default(0);

            $t->string('numero_resolucion', 40)->nullable();
            $t->text('clave_tecnica')->nullable();                // encrypted (para CUFE)
            $t->date('fecha_desde')->nullable();
            $t->date('fecha_hasta')->nullable();

            $t->boolean('activa')->default(true);
            $t->timestamps();

            $t->index(['tenant_id', 'tipo_documento', 'activa']);
        });
    }

    public function down(): void { Schema::dropIfExists('fe_resoluciones'); }
};
