<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $t) {
            // FB App ID — necesario para Resumable Upload API (templates con media headers)
            $t->string('app_id', 32)->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_configs', function (Blueprint $t) {
            $t->dropColumn('app_id');
        });
    }
};
