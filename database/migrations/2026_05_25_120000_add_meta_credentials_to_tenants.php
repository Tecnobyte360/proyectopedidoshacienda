<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'meta_app_id')) {
                $t->string('meta_app_id', 60)->nullable();
            }
            if (!Schema::hasColumn('tenants', 'meta_app_secret')) {
                $t->text('meta_app_secret')->nullable(); // encrypted
            }
            if (!Schema::hasColumn('tenants', 'ig_client_id')) {
                $t->string('ig_client_id', 60)->nullable();
            }
            if (!Schema::hasColumn('tenants', 'ig_client_secret')) {
                $t->text('ig_client_secret')->nullable(); // encrypted
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['meta_app_id','meta_app_secret','ig_client_id','ig_client_secret'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });
    }
};
