<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🚫 Bloqueo soft por mora.
 *
 * Antes el cron seteaba tenant.activo=false → bloqueaba login completo.
 * Ahora usamos un campo separado: `suspendido_por_mora`. El tenant puede
 * iniciar sesión pero el middleware lo redirige a /billing/expirado.
 *
 * `activo` queda solo para suspensión administrativa manual del super-admin.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'suspendido_por_mora')) {
                $t->boolean('suspendido_por_mora')->default(false)->after('activo');
            }
            if (!Schema::hasColumn('tenants', 'suspendido_at')) {
                $t->timestamp('suspendido_at')->nullable()->after('suspendido_por_mora');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (Schema::hasColumn('tenants', 'suspendido_por_mora')) $t->dropColumn('suspendido_por_mora');
            if (Schema::hasColumn('tenants', 'suspendido_at'))      $t->dropColumn('suspendido_at');
        });
    }
};
