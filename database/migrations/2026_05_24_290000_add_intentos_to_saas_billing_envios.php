<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('saas_billing_envios', function (Blueprint $t) {
            if (!Schema::hasColumn('saas_billing_envios', 'intentos')) {
                $t->unsignedSmallInteger('intentos')->default(1)->after('ok');
            }
            if (!Schema::hasColumn('saas_billing_envios', 'ultimo_intento_at')) {
                $t->timestamp('ultimo_intento_at')->nullable()->after('intentos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saas_billing_envios', function (Blueprint $t) {
            if (Schema::hasColumn('saas_billing_envios', 'intentos')) $t->dropColumn('intentos');
            if (Schema::hasColumn('saas_billing_envios', 'ultimo_intento_at')) $t->dropColumn('ultimo_intento_at');
        });
    }
};
