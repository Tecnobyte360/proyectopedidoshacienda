<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🖨️ Impresoras registradas (una por PC/agente). El agente de Windows
        //    se autentica con su `token` y pregunta si hay trabajos pendientes.
        Schema::create('impresoras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('nombre')->default('Impresora');          // nombre visible en Kivox
            $table->string('printer_name')->nullable();               // nombre en Windows (ej. EPSON TM-T...)
            $table->string('token', 64)->unique();                    // secreto que usa el agente
            $table->string('pc_nombre')->nullable();                  // ej. ALH-PC-042
            $table->boolean('activa')->default(true);
            $table->timestamp('ultima_conexion_at')->nullable();      // último poll del agente
            $table->timestamps();
        });

        // 🧾 Cola de trabajos de impresión. La tablet encola, el agente imprime.
        Schema::create('trabajos_impresion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('impresora_id')->index();
            $table->unsignedBigInteger('pedido_id')->nullable()->index();
            $table->string('tipo')->default('ticket');                // ticket | prueba
            $table->longText('contenido');                            // texto/ESC-POS a imprimir
            $table->string('estado')->default('pendiente')->index();  // pendiente | enviado | impreso | error
            $table->unsignedInteger('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('impreso_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_impresion');
        Schema::dropIfExists('impresoras');
    }
};
