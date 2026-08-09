<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $table) {
            // ID del catálogo de Meta (Commerce Manager) para carrito nativo en WhatsApp.
            $table->string('catalog_id', 64)->nullable()->after('waba_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $table) {
            $table->dropColumn('catalog_id');
        });
    }
};
