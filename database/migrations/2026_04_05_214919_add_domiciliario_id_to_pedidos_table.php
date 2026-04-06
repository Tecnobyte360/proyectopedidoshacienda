<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('domiciliario_id')
                ->nullable()
                ->after('sede_id')
                ->constrained('domiciliarios')
                ->nullOnDelete();

            $table->timestamp('fecha_asignacion_domiciliario')->nullable()->after('fecha_estado');
            $table->timestamp('fecha_salida_domiciliario')->nullable()->after('fecha_asignacion_domiciliario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domiciliario_id');
            $table->dropColumn([
                'fecha_asignacion_domiciliario',
                'fecha_salida_domiciliario',
            ]);
        });
    }
};