<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('felicitaciones_cumpleanos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->string('cliente_nombre', 120);
            $table->string('telefono', 30);

            // enviado | fallido | dry_run
            $table->string('estado', 20)->index();

            $table->text('mensaje');

            // Si falló, aquí guardamos el motivo
            $table->text('error_detalle')->nullable();

            // Quién disparó la corrida: scheduled | manual | force
            $table->string('origen', 20)->default('scheduled');

            $table->unsignedSmallInteger('anio');
            $table->timestamp('enviado_at')->useCurrent();

            $table->timestamps();

            $table->index(['anio', 'estado']);
            $table->index('enviado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('felicitaciones_cumpleanos');
    }
};
