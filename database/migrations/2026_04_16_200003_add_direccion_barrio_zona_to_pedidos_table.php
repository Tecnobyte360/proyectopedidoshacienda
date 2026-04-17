<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('cliente_nombre');
            $table->string('barrio')->nullable()->after('direccion');

            $table->foreignId('zona_cobertura_id')
                ->nullable()
                ->after('barrio')
                ->constrained('zonas_cobertura')
                ->nullOnDelete();

            $table->index('barrio');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['zona_cobertura_id']);
            $table->dropIndex(['barrio']);
            $table->dropColumn(['direccion', 'barrio', 'zona_cobertura_id']);
        });
    }
};
