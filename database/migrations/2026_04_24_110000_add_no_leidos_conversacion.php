<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'no_leidos')) {
                $table->integer('no_leidos')->default(0)->after('total_mensajes_bot');
            }
            if (!Schema::hasColumn('conversaciones_whatsapp', 'ultima_vista_at')) {
                $table->timestamp('ultima_vista_at')->nullable()->after('no_leidos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('conversaciones_whatsapp', 'no_leidos')) {
                $table->dropColumn(['no_leidos', 'ultima_vista_at']);
            }
        });
    }
};
