<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'whatsapp_provider')) {
                $table->enum('whatsapp_provider', ['auto', 'meta', 'tecnobyte'])
                    ->default('auto')
                    ->after('whatsapp_config');
            }
            if (!Schema::hasColumn('tenants', 'whatsapp_fallback_enabled')) {
                $table->boolean('whatsapp_fallback_enabled')
                    ->default(true)
                    ->after('whatsapp_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'whatsapp_fallback_enabled')) {
                $table->dropColumn('whatsapp_fallback_enabled');
            }
            if (Schema::hasColumn('tenants', 'whatsapp_provider')) {
                $table->dropColumn('whatsapp_provider');
            }
        });
    }
};
