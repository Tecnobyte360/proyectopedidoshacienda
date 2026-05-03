<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refactor: cada sede tiene su propia cobertura (polígono, costo,
     * tiempo, mínimo). Antes era una entidad ZonaCobertura separada.
     *
     * Mantiene compatibilidad: la tabla zonas_cobertura sigue existiendo
     * pero queda como "modo legacy". El bot consultará primero las
     * coberturas en sedes.
     */
    public function up(): void
    {
        // 1. Agregar columnas de cobertura a sedes
        Schema::table('sedes', function (Blueprint $table) {
            $table->json('cobertura_poligono')->nullable()->after('horarios');
            $table->decimal('cobertura_costo_envio', 12, 2)->default(0)->after('cobertura_poligono');
            $table->unsignedSmallInteger('cobertura_tiempo_min')->default(45)->after('cobertura_costo_envio');
            $table->decimal('cobertura_pedido_minimo', 12, 2)->default(0)->after('cobertura_tiempo_min');
            $table->string('cobertura_color', 10)->default('#d68643')->after('cobertura_pedido_minimo');
            $table->text('cobertura_descripcion')->nullable()->after('cobertura_color');
            $table->boolean('cobertura_activa')->default(true)->after('cobertura_descripcion');

            // Centro del mapa para la sede (donde inicializa el editor visual)
            $table->decimal('cobertura_centro_lat', 10, 7)->nullable()->after('cobertura_activa');
            $table->decimal('cobertura_centro_lng', 10, 7)->nullable()->after('cobertura_centro_lat');
        });

        // 2. Migrar datos de zonas_cobertura → sedes
        // Estrategia: para cada sede, busca la zona con su sede_id; si no
        // hay, busca la primera zona global (sede_id NULL). Copia el polígono.
        if (Schema::hasTable('zonas_cobertura')) {
            $sedes = DB::table('sedes')->where('activa', true)->get();
            $zonaGlobal = DB::table('zonas_cobertura')
                ->where('activa', true)
                ->whereNull('sede_id')
                ->whereNotNull('poligono')
                ->first();

            foreach ($sedes as $sede) {
                // Buscar zona específica de esta sede
                $zonaPropia = DB::table('zonas_cobertura')
                    ->where('activa', true)
                    ->where('sede_id', $sede->id)
                    ->whereNotNull('poligono')
                    ->first();

                $zona = $zonaPropia ?: $zonaGlobal;

                if (!$zona) continue;

                DB::table('sedes')->where('id', $sede->id)->update([
                    'cobertura_poligono'        => $zona->poligono,
                    'cobertura_costo_envio'     => $zona->costo_envio ?? 0,
                    'cobertura_tiempo_min'      => $zona->tiempo_estimado_min ?? 45,
                    'cobertura_pedido_minimo'   => $zona->pedido_minimo ?? 0,
                    'cobertura_color'           => $zona->color ?? '#d68643',
                    'cobertura_descripcion'     => $zona->descripcion ?? null,
                    'cobertura_activa'          => true,
                    'cobertura_centro_lat'      => $zona->centro_lat ?? null,
                    'cobertura_centro_lng'      => $zona->centro_lng ?? null,
                    'updated_at'                => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn([
                'cobertura_poligono',
                'cobertura_costo_envio',
                'cobertura_tiempo_min',
                'cobertura_pedido_minimo',
                'cobertura_color',
                'cobertura_descripcion',
                'cobertura_activa',
                'cobertura_centro_lat',
                'cobertura_centro_lng',
            ]);
        });
    }
};
