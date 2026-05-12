<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_whatsapp_plantillas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('nombre', 128);                    // EJ: pedido_recibido
            $table->string('idioma', 12)->default('es');      // BCP-47: es, es_CO, en_US
            $table->string('categoria', 32)->default('UTILITY'); // UTILITY | MARKETING | AUTHENTICATION
            $table->string('estado', 32)->default('borrador');// borrador | aprobada | rechazada | pendiente (de Meta)
            $table->string('descripcion', 255)->nullable();
            $table->text('body_preview')->nullable();         // texto del BODY con {{1}} {{2}}
            $table->text('footer')->nullable();
            $table->unsignedTinyInteger('num_variables')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre', 'idioma']);
        });

        Schema::create('meta_whatsapp_disparadores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('evento', 64);                     // EJ: pedido_confirmado, pedido_entregado, encuesta
            $table->unsignedBigInteger('plantilla_id');
            $table->json('variables_map')->nullable();        // {1: "{cliente_nombre}", 2: "{total}"}
            $table->boolean('activo')->default(true);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'evento']);
            $table->foreign('plantilla_id')->references('id')->on('meta_whatsapp_plantillas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_disparadores');
        Schema::dropIfExists('meta_whatsapp_plantillas');
    }
};
