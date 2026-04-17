<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('barrio');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['lat', 'lng']);
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
