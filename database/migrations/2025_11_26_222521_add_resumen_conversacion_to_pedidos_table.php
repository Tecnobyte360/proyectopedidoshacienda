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
        $table->text('resumen_conversacion')->nullable()->after('conversacion_completa');
    });
}

public function down(): void
{
    Schema::table('pedidos', function (Blueprint $table) {
        $table->dropColumn('resumen_conversacion');
    });
}

};
