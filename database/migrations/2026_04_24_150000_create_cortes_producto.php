<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Maestro de cortes por tenant (idempotente)
        if (!Schema::hasTable('cortes')) {
            Schema::create('cortes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('nombre', 80);
                $table->string('descripcion', 250)->nullable();
                $table->string('icono_emoji', 10)->nullable();
                $table->integer('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'nombre'], 'uniq_tenant_corte');
                $table->index(['tenant_id', 'activo']);
            });
        }

        // Pivot: qué cortes aplican a cada producto
        if (!Schema::hasTable('producto_corte')) {
            Schema::create('producto_corte', function (Blueprint $table) {
                $table->id();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->foreignId('corte_id')->constrained('cortes')->cascadeOnDelete();
                $table->integer('orden')->default(0);
                $table->timestamps();

                $table->unique(['producto_id', 'corte_id'], 'uniq_prod_corte');
            });
        }

        // Agregar corte al detalle del pedido — solo si la tabla existe y la columna no.
        // En algunas instalaciones la tabla es 'detalles_pedido', en otras 'detalle_pedidos'.
        $tabla = Schema::hasTable('detalles_pedido')
            ? 'detalles_pedido'
            : (Schema::hasTable('detalle_pedidos') ? 'detalle_pedidos' : null);

        if ($tabla && !Schema::hasColumn($tabla, 'corte_nombre')) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('corte_nombre', 80)->nullable()->after('unidad');
            });
        }
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            if (Schema::hasColumn('detalles_pedido', 'corte_nombre')) {
                $table->dropColumn('corte_nombre');
            }
        });
        Schema::dropIfExists('producto_corte');
        Schema::dropIfExists('cortes');
    }
};
