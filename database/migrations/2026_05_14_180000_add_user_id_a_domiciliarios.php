<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('domiciliarios', function (Blueprint $t) {
            if (!Schema::hasColumn('domiciliarios', 'user_id')) {
                $t->unsignedBigInteger('user_id')->nullable()->after('id')
                    ->comment('User asociado para que ingrese al sistema con rol domiciliario');
                $t->index('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domiciliarios', function (Blueprint $t) {
            if (Schema::hasColumn('domiciliarios', 'user_id')) {
                $t->dropIndex(['user_id']);
                $t->dropColumn('user_id');
            }
        });
    }
};
