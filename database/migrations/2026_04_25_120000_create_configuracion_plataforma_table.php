<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configuracion_plataforma')) return;

        Schema::create('configuracion_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->default('TecnoByte360');
            $table->string('subtitulo', 120)->default('Plataforma SaaS');
            $table->string('color_primario', 12)->default('#d68643');
            $table->string('color_secundario', 12)->default('#a85f24');
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('email_soporte', 120)->nullable();
            $table->string('telefono_soporte', 30)->nullable();
            $table->string('sitio_web', 200)->nullable();
            $table->timestamps();
        });

        // Insertar la fila singleton (id=1) con defaults
        \DB::table('configuracion_plataforma')->insert([
            'id'                 => 1,
            'nombre'             => 'TecnoByte360',
            'subtitulo'          => 'Plataforma SaaS',
            'color_primario'     => '#d68643',
            'color_secundario'   => '#a85f24',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_plataforma');
    }
};
