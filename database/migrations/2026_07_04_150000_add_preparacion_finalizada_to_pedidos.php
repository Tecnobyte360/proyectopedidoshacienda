<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'preparacion_finalizada')) {
                $table->boolean('preparacion_finalizada')->default(false)->index()->after('estado');
            }
            if (!Schema::hasColumn('pedidos', 'preparacion_finalizada_at')) {
                $table->timestamp('preparacion_finalizada_at')->nullable()->after('preparacion_finalizada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['preparacion_finalizada', 'preparacion_finalizada_at']);
        });
    }
};
