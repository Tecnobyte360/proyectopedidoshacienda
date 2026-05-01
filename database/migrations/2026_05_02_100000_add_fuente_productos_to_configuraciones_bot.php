<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que el bot decida de donde leer los productos:
     *   - 'tabla'       : la tabla productos local (default)
     *   - 'integracion' : auto-sincroniza desde una integracion antes de leer
     */
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->string('fuente_productos', 20)->default('tabla')->after('connection_id_default');
            $table->unsignedBigInteger('integracion_productos_id')->nullable()->after('fuente_productos');
            $table->unsignedSmallInteger('auto_sync_productos_min')->default(15)->after('integracion_productos_id');
            $table->timestamp('ultimo_sync_productos_at')->nullable()->after('auto_sync_productos_min');

            $table->foreign('integracion_productos_id')
                  ->references('id')->on('integraciones')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropForeign(['integracion_productos_id']);
            $table->dropColumn([
                'fuente_productos',
                'integracion_productos_id',
                'auto_sync_productos_min',
                'ultimo_sync_productos_at',
            ]);
        });
    }
};
