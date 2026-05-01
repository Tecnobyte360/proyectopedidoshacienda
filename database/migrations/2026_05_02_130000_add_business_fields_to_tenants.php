<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de negocio del tenant para hacer dinámico el prompt del bot.
     * Sin estos, los prompts terminan teniendo nombres hardcoded como
     * "Alimentos La Hacienda en Bello" — al cambiar de tenant rompe.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('ciudad', 80)->nullable()->after('contacto_telefono');
            $table->string('tipo_negocio', 40)->nullable()->after('ciudad');
            $table->string('slogan', 200)->nullable()->after('tipo_negocio');
            $table->text('descripcion_negocio')->nullable()->after('slogan');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['ciudad', 'tipo_negocio', 'slogan', 'descripcion_negocio']);
        });
    }
};
