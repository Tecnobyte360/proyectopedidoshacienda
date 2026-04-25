<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuraciones_bot', 'bot_zonas_ids')) {
            Schema::table('configuraciones_bot', function (Blueprint $table) {
                $table->json('bot_zonas_ids')->nullable()->after('info_empresa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuraciones_bot', 'bot_zonas_ids')) {
            Schema::table('configuraciones_bot', function (Blueprint $table) {
                $table->dropColumn('bot_zonas_ids');
            });
        }
    }
};
