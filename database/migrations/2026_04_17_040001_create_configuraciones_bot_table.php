<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_bot', function (Blueprint $table) {
            $table->id();

            // Comportamiento del bot
            $table->boolean('enviar_imagenes_productos')->default(false);
            $table->unsignedSmallInteger('max_imagenes_por_mensaje')->default(3);
            $table->boolean('enviar_imagen_destacados')->default(false);
            $table->boolean('saludar_con_promociones')->default(true);

            // OpenAI
            $table->string('modelo_openai', 60)->default('gpt-4o-mini');
            $table->decimal('temperatura', 3, 2)->default(0.85);
            $table->unsignedSmallInteger('max_tokens')->default(700);

            // Identidad
            $table->string('nombre_asesora', 60)->default('Sofía');
            $table->text('frase_bienvenida')->nullable();

            // Otros
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });

        // Crear la fila singleton inicial
        DB::table('configuraciones_bot')->insert([
            'enviar_imagenes_productos' => false,
            'max_imagenes_por_mensaje'  => 3,
            'enviar_imagen_destacados'  => false,
            'saludar_con_promociones'   => true,
            'modelo_openai'             => 'gpt-4o-mini',
            'temperatura'               => 0.85,
            'max_tokens'                => 700,
            'nombre_asesora'            => 'Sofía',
            'frase_bienvenida'          => null,
            'activo'                    => true,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_bot');
    }
};
