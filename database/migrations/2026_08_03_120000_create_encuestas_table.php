<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encuestas PERSONALIZABLES del tenant (constructor tipo "form builder").
 * Distinta de `encuestas_pedido` (la de satisfacción post-entrega, fija).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('encuestas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('nombre');
            $t->text('descripcion')->nullable();
            $t->string('token', 40)->unique();              // link público: /e/{token}
            $t->boolean('activa')->default(true);
            $t->string('mensaje_gracias', 300)->nullable(); // texto tras responder
            $t->timestamps();

            $t->index(['tenant_id', 'activa']);
        });
    }

    public function down(): void { Schema::dropIfExists('encuestas'); }
};
