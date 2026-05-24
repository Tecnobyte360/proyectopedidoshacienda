<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchdog_rescates', function (Blueprint $table) {
            if (!Schema::hasColumn('watchdog_rescates', 'resuelto_at')) {
                $table->timestamp('resuelto_at')->nullable()->after('exitoso');
            }
            if (!Schema::hasColumn('watchdog_rescates', 'resuelto_por_user_id')) {
                $table->unsignedBigInteger('resuelto_por_user_id')->nullable()->after('resuelto_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('watchdog_rescates', function (Blueprint $table) {
            if (Schema::hasColumn('watchdog_rescates', 'resuelto_por_user_id')) {
                $table->dropColumn('resuelto_por_user_id');
            }
            if (Schema::hasColumn('watchdog_rescates', 'resuelto_at')) {
                $table->dropColumn('resuelto_at');
            }
        });
    }
};
