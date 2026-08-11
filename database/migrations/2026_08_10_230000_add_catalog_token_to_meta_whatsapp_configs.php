<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $table) {
            // Token de catálogo (System User) para sincronizar productos en tiempo
            // real con Meta (Catalog Batch API). Distinto del access_token de WhatsApp.
            $table->text('catalog_token')->nullable()->after('catalog_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $table) {
            $table->dropColumn('catalog_token');
        });
    }
};
