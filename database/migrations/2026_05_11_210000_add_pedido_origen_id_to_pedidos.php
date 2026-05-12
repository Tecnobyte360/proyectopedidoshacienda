<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_origen_id')->nullable()->after('id');
            $table->index('pedido_origen_id');
            $table->foreign('pedido_origen_id')
                ->references('id')->on('pedidos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['pedido_origen_id']);
            $table->dropIndex(['pedido_origen_id']);
            $table->dropColumn('pedido_origen_id');
        });
    }
};
