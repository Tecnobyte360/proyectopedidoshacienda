<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->text('info_empresa')->nullable()->after('frase_bienvenida');
        });

        // Pre-llenar con la info actual hardcoded del bot
        \DB::table('configuraciones_bot')->where('id', 1)->update([
            'info_empresa' => "Alimentos La Hacienda\n"
                . "- Más de 25 años de experiencia.\n"
                . "- Ubicada en Bello, Antioquia.\n"
                . "- Calidad, frescura y servicio al cliente.\n"
                . "- Opera con domicilios, sedes físicas y atención directa.\n"
                . "- Sistema de pedidos integrado.",
        ]);
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('info_empresa');
        });
    }
};
