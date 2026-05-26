<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'instagram_access_token')) {
                $t->text('instagram_access_token')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'instagram_token_expira_at')) {
                $t->timestamp('instagram_token_expira_at')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'instagram_username')) {
                $t->string('instagram_username', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['instagram_access_token','instagram_token_expira_at','instagram_username'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });
    }
};
