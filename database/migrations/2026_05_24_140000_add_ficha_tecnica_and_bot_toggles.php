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

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'enviar_ficha_tecnica')) {
                $table->boolean('enviar_ficha_tecnica')->default(false);
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

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'enviar_ficha_tecnica')) {
                $table->dropColumn('enviar_ficha_tecnica');
            }
        });
    }
};
