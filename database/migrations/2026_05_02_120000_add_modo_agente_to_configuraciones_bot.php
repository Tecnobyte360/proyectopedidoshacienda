<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuando bot_modo_agente=true, el sistema:
     *  - NO inyecta el catálogo completo en el system_prompt.
     *  - Expone tools (buscar_productos, listar_categorias, info_producto,
     *    productos_de_categoria, productos_destacados) que el LLM llama según
     *    necesite.
     *
     * Reduce drásticamente el contexto y mejora precisión con catálogos grandes.
     */
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->boolean('bot_modo_agente')->default(false)->after('excluir_productos_sin_precio');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('bot_modo_agente');
        });
    }
};
