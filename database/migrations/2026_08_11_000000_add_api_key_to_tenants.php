<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // API key propia del tenant para que sistemas externos se integren
            // (ej. enviar plantillas de WhatsApp). Identifica al tenant y sale
            // desde SU número de WhatsApp.
            $table->string('api_key', 80)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
