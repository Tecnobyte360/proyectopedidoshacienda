<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Limpieza preventiva idempotente — si quedan duplicados de runs anteriores,
        // borrarlos antes de crear el índice único.
        if (Schema::hasTable('productos')) {
            DB::statement("
                DELETE p1 FROM productos p1
                INNER JOIN productos p2
                ON p1.tenant_id = p2.tenant_id
                  AND TRIM(LEADING '0' FROM p1.codigo) = TRIM(LEADING '0' FROM p2.codigo)
                  AND p1.codigo IS NOT NULL AND p1.codigo != ''
                  AND p1.id > p2.id
            ");

            // Normalizar códigos numéricos con ceros a la izquierda
            DB::statement("UPDATE productos SET codigo = TRIM(LEADING '0' FROM codigo) WHERE codigo REGEXP '^0[0-9]+$'");
        }

        Schema::table('productos', function (Blueprint $table) {
            // Índice único parcial no existe en MySQL — usamos índice único compuesto.
            // Para evitar bloqueo en rows con código vacío/null, los normalizamos a NULL.
            $table->unique(['tenant_id', 'codigo'], 'productos_tenant_codigo_unique');
        });

        // MySQL trata NULL como distinto en unique indexes, así que productos sin
        // código pueden coexistir. Pero códigos vacíos '' chocarían — los convertimos a NULL.
        DB::statement("UPDATE productos SET codigo = NULL WHERE codigo = ''");
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique('productos_tenant_codigo_unique');
        });
    }
};
