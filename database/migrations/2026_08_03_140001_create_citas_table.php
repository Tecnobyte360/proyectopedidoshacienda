<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citas / agenda del tenant (ej. sesiones de psicología de YoConsciente).
 * Se sincroniza con Google Calendar en fase 2 (google_event_id).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->unsignedBigInteger('cliente_id')->nullable();
            $t->string('paciente_nombre', 150);
            $t->string('paciente_telefono', 30)->nullable();
            $t->string('paciente_email', 150)->nullable();
            $t->dateTime('inicio_at');
            $t->dateTime('fin_at');
            $t->string('estado', 20)->default('confirmada'); // pendiente|confirmada|cancelada|completada
            $t->string('motivo', 200)->nullable();
            $t->text('notas')->nullable();
            $t->string('origen', 20)->default('panel');       // panel|whatsapp
            $t->string('google_event_id')->nullable();
            $t->unsignedBigInteger('creado_por')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'inicio_at']);
            $t->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void { Schema::dropIfExists('citas'); }
};
