<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones_ivr', function (Blueprint $t) {
            $t->id();
            $t->foreignId('llamada_id')->nullable()->constrained('llamadas_ivr')->cascadeOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->string('asterisk_uniqueid', 60)->index();
            $t->string('caller_id', 30);
            $t->json('historial');             // array de turnos [{role, content, ts}]
            $t->json('acciones_ejecutadas')->nullable(); // qué tools llamó la IA
            $t->string('estado', 30)->default('activa'); // activa | finalizada | transferida
            $t->integer('turnos')->default(0);
            $t->decimal('costo_usd', 8, 4)->default(0);
            $t->timestamps();

            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversaciones_ivr');
    }
};
