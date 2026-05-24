<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'ficha_tecnica_url')) {
                $table->string('ficha_tecnica_url', 500)->nullable()->after('imagen_url');
            }
        });

        Schema::table('configuracion_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuracion_bot', 'enviar_ficha_tecnica')) {
                $table->boolean('enviar_ficha_tecnica')->default(false)->after('activo');
            }
            if (!Schema::hasColumn('configuracion_bot', 'enviar_imagenes_productos')) {
                $table->boolean('enviar_imagenes_productos')->default(true)->after('enviar_ficha_tecnica');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'ficha_tecnica_url')) {
                $table->dropColumn('ficha_tecnica_url');
            }
        });

        Schema::table('configuracion_bot', function (Blueprint $table) {
            foreach (['enviar_imagenes_productos', 'enviar_ficha_tecnica'] as $col) {
                if (Schema::hasColumn('configuracion_bot', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
