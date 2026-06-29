<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'bot_modo_menu')) {
                $table->boolean('bot_modo_menu')->default(false)->after('bot_modo_agente');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'menu_json')) {
                $table->longText('menu_json')->nullable()->after('bot_modo_menu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'menu_json')) {
                $table->dropColumn('menu_json');
            }
            if (Schema::hasColumn('configuraciones_bot', 'bot_modo_menu')) {
                $table->dropColumn('bot_modo_menu');
            }
        });
    }
};
