<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'instrucciones_extra')) {
                $table->text('instrucciones_extra')->nullable()->after('system_prompt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'instrucciones_extra')) {
                $table->dropColumn('instrucciones_extra');
            }
        });
    }
};
