<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('codigo_seguimiento', 100)->nullable()->unique()->after('estado');
            $table->timestamp('fecha_estado')->nullable()->after('codigo_seguimiento');
            $table->timestamp('fecha_entregado')->nullable()->after('fecha_estado');
            $table->timestamp('fecha_cancelado')->nullable()->after('fecha_entregado');
            $table->text('observacion_estado')->nullable()->after('fecha_cancelado');
        });

        DB::table('pedidos')->orderBy('id')->chunkById(100, function ($pedidos) {
            foreach ($pedidos as $pedido) {
                DB::table('pedidos')
                    ->where('id', $pedido->id)
                    ->update([
                        'codigo_seguimiento' => Str::uuid(),
                        'fecha_estado' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_seguimiento',
                'fecha_estado',
                'fecha_entregado',
                'fecha_cancelado',
                'observacion_estado',
            ]);
        });
    }
};