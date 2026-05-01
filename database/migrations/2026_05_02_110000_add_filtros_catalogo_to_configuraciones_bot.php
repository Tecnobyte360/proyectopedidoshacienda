<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filtros para el catalogo que ve el bot:
     *  - categorias_excluidas_bot: array de categorias (string) a omitir.
     *  - excluir_productos_sin_precio: si true, omite productos con precio <= 0.
     *
     * Esto evita que el bot reciba miles de items irrelevantes (BOLSAS,
     * INSUMOS, IMPUESTOS, etc.) que vienen del ERP y saturan al LLM.
     */
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->json('categorias_excluidas_bot')->nullable()->after('auto_sync_productos_min');
            $table->boolean('excluir_productos_sin_precio')->default(true)->after('categorias_excluidas_bot');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn(['categorias_excluidas_bot', 'excluir_productos_sin_precio']);
        });
    }
};
