<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Agregar campos después de cliente_nombre
            $table->string('telefono', 20)->nullable()->after('cliente_nombre');
            $table->string('canal', 50)->default('whatsapp')->after('telefono'); 
            $table->text('conversacion_completa')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'canal', 'conversacion_completa']);
        });
    }
};