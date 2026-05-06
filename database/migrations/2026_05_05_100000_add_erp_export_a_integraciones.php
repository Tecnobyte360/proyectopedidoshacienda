<?php

use App\Models\Integracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integraciones', function (Blueprint $table) {
            // Si la integración exporta pedidos al ERP cuando se confirman.
            // 'config' (json) llevará el mapeo de campos: empresa_id, transaccion,
            // bodega, cartera, usuario_grabador, etc, según TblDocumentos.
            $table->boolean('exporta_pedidos')->default(false)
                ->after('activo')
                ->comment('Si true, los pedidos confirmados se insertan en la tabla destino vía SQL');
        });

        // Agregar también la tabla de logs de export para auditar
        if (!Schema::hasTable('integracion_export_logs')) {
            Schema::create('integracion_export_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('integracion_id')->index();
                $table->unsignedBigInteger('pedido_id')->index();
                $table->string('estado', 20); // ok, error
                $table->string('documento_id', 50)->nullable(); // IntDocumento del ERP
                $table->text('sql_ejecutado')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('integraciones', function (Blueprint $table) {
            $table->dropColumn('exporta_pedidos');
        });
        Schema::dropIfExists('integracion_export_logs');
    }
};
