<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('nombre', 120);                      // "ERP Producción", "POS Sucursal Bello"
            $table->string('tipo', 50);                         // mysql | pgsql | sqlsrv | rest
            $table->string('entidad', 50)->default('productos');// qué sincroniza: productos | categorias

            // Config (cifrado sería ideal, por ahora JSON normal):
            //   Para SQL: { host, port, database, username, password, query, mapeo: {codigo: "col_cod", nombre: "col_nom", ...} }
            //   Para REST: { url, headers, method, jsonpath, mapeo: {...} }
            $table->json('config');

            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->string('ultima_sincronizacion_estado', 20)->nullable();   // ok | error
            $table->text('ultima_sincronizacion_log')->nullable();
            $table->integer('total_registros_ultima_sync')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integraciones');
    }
};
