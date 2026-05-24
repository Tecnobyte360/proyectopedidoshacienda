<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 📞 Permisos de llamada por cliente.
 *
 * Meta exige que el cliente acepte una "call_permission_request" antes
 * de la primera llamada saliente. El permiso vence (Meta lo define,
 * típicamente 7 días después del último intercambio).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_call_permissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('telefono', 25);
            $t->string('estado', 24)->default('pending'); // pending | accepted | rejected | expired
            $t->timestamp('solicitado_at')->nullable();
            $t->timestamp('respondido_at')->nullable();
            $t->timestamp('expira_at')->nullable();
            $t->json('payload')->nullable();
            $t->timestamps();

            $t->unique(['tenant_id', 'telefono']);
            $t->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_call_permissions');
    }
};
