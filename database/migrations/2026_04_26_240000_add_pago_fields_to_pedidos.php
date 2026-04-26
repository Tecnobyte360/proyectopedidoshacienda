<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'estado_pago')) {
                // pendiente | aprobado | rechazado | fallido | reembolsado | sin_pago
                $table->string('estado_pago', 30)->default('pendiente')->after('estado');
                $table->index('estado_pago');
            }
            if (!Schema::hasColumn('pedidos', 'wompi_reference')) {
                $table->string('wompi_reference', 80)->nullable()->after('estado_pago');
                $table->index('wompi_reference');
            }
            if (!Schema::hasColumn('pedidos', 'wompi_transaction_id')) {
                $table->string('wompi_transaction_id', 80)->nullable()->after('wompi_reference');
            }
            if (!Schema::hasColumn('pedidos', 'pago_metodo')) {
                $table->string('pago_metodo', 40)->nullable()->after('wompi_transaction_id');
            }
            if (!Schema::hasColumn('pedidos', 'pagado_at')) {
                $table->timestamp('pagado_at')->nullable()->after('pago_metodo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['estado_pago', 'wompi_reference', 'wompi_transaction_id', 'pago_metodo', 'pagado_at'] as $c) {
                if (Schema::hasColumn('pedidos', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
