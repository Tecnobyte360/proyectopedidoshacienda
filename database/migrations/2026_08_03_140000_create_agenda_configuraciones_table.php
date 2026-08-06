<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de agenda por tenant (horarios, duración de cita) + credenciales
 * de Google Calendar (se llenan en la fase 2; nullable por ahora).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('agenda_configuraciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $t->unsignedSmallInteger('duracion_min')->default(60);   // duración de la cita
            $t->unsignedSmallInteger('buffer_min')->default(0);      // descanso entre citas
            $t->json('dias')->nullable();                            // [1..7] días hábiles (1=lun)
            $t->time('hora_inicio')->default('08:00');
            $t->time('hora_fin')->default('18:00');
            $t->string('zona_horaria', 40)->default('America/Bogota');
            $t->boolean('activa')->default(true);

            // ── Google Calendar (fase 2) ──
            $t->boolean('google_conectado')->default(false);
            $t->string('google_calendar_id')->nullable();
            $t->text('google_access_token')->nullable();     // encrypted
            $t->text('google_refresh_token')->nullable();    // encrypted
            $t->timestamp('google_token_expira_at')->nullable();
            $t->string('google_cuenta_email')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('agenda_configuraciones'); }
};
