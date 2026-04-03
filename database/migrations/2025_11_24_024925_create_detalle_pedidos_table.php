<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalles_pedido', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('producto', 190);
            $table->decimal('cantidad', 10, 3)->default(0); // soporta 0.500 kg, etc.
            $table->string('unidad', 50)->default('kilo');   // kilo, gramos, libras, unidades

            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_pedido');
    }
};
