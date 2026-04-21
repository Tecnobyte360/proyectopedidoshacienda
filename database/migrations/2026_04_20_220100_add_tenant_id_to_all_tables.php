<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas que obtienen tenant_id directo. Se omiten las que heredan tenant
     * a través de otra tabla (ej. detalles_pedidos hereda via pedidos.tenant_id).
     */
    private array $tablas = [
        'pedidos',
        'clientes',
        'productos',
        'categorias',
        'promociones',
        'zonas_cobertura',
        'sedes',
        'domiciliarios',
        'ans_pedidos',
        'bot_alertas',
        'felicitaciones_cumpleanos',
        'beneficios_clientes',
        'configuraciones_bot',
        'conversaciones_whatsapp',
        'users',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (!Schema::hasTable($tabla)) continue;
            if (Schema::hasColumn($tabla, 'tenant_id')) continue;

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                // FK con nullOnDelete para que si se borra un tenant, no rompa
                // (en producción real probablemente quieres cascade, pero para
                // soft-delete y safety, nullOnDelete es más conservador).
                $table->foreign('tenant_id')
                      ->references('id')->on('tenants')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (!Schema::hasTable($tabla)) continue;
            if (!Schema::hasColumn($tabla, 'tenant_id')) continue;

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                try {
                    $table->dropForeign(["{$tabla}_tenant_id_foreign"]);
                } catch (\Throwable $e) {}
                $table->dropColumn('tenant_id');
            });
        }
    }
};
