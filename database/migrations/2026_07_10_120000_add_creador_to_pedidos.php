<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            if (!Schema::hasColumn('pedidos', 'usuario_creador_id')) {
                $t->unsignedBigInteger('usuario_creador_id')->nullable()->after('sede_id');
            }
            if (!Schema::hasColumn('pedidos', 'creado_por_sede_id')) {
                $t->unsignedBigInteger('creado_por_sede_id')->nullable()->after('usuario_creador_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            foreach (['usuario_creador_id', 'creado_por_sede_id'] as $c) {
                if (Schema::hasColumn('pedidos', $c)) $t->dropColumn($c);
            }
        });
    }
};
