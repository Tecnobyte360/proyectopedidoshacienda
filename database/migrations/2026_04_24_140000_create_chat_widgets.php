<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('token', 40)->unique();          // para identificar el widget en el script público
            $table->string('nombre', 120);                  // "Widget tienda online", "Widget landing"

            // Branding visual
            $table->string('color_primario', 20)->default('#d68643');
            $table->string('color_secundario', 20)->default('#a85f24');
            $table->string('posicion', 20)->default('bottom-right');   // bottom-right | bottom-left
            $table->string('titulo', 120)->default('¿En qué te ayudamos?');
            $table->string('subtitulo', 200)->nullable();
            $table->text('saludo_inicial')->nullable();
            $table->string('placeholder', 120)->default('Escribe un mensaje...');
            $table->string('avatar_url', 500)->nullable();

            // Dominios autorizados (CSV) para evitar que cualquiera use el script
            $table->text('dominios_permitidos')->nullable();

            // Configuración de comportamiento
            $table->boolean('activo')->default(true);
            $table->boolean('pedir_nombre')->default(true);
            $table->boolean('pedir_telefono')->default(false);
            $table->boolean('sonido_notificacion')->default(true);

            $table->integer('total_conversaciones')->default(0);
            $table->integer('total_mensajes')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'activo']);
        });

        Schema::create('chat_widget_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('session_id', 60)->unique();     // UUID generado por el browser
            $table->string('visitante_nombre', 120)->nullable();
            $table->string('visitante_telefono', 30)->nullable();
            $table->string('visitante_email', 150)->nullable();
            $table->string('url_origen', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();

            $table->integer('total_mensajes')->default(0);
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();

            $table->index(['widget_id', 'session_id']);
        });

        Schema::create('chat_widget_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('chat_widget_sesiones')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('rol', 20);                       // user | assistant | system
            $table->text('contenido');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sesion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_widget_mensajes');
        Schema::dropIfExists('chat_widget_sesiones');
        Schema::dropIfExists('chat_widgets');
    }
};
