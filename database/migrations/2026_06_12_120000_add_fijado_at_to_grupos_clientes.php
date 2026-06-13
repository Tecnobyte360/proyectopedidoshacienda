<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos_clientes', function (Blueprint $t) {
            $t->timestamp('fijado_at')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_clientes', function (Blueprint $t) {
            $t->dropColumn('fijado_at');
        });
    }
};
