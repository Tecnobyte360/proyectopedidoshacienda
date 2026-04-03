<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->dateTime('fecha_pedido');
            $table->time('hora_entrega')->nullable();

            $table->string('estado', 50)->default('pendiente');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('cliente_nombre', 190)->nullable();
            $table->text('notas')->nullable();

            // Si luego quieres saber canal del pedido:
            // $table->string('canal', 50)->nullable(); // whatsapp, app, web, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
