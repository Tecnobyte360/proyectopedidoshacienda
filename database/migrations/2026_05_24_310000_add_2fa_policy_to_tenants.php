<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔐 Política 2FA por tenant.
 *
 * - requiere_2fa: si true, todos los usuarios deben activar 2FA
 * - gracia_2fa_dias: días que tiene el usuario desde el primer login después
 *   de activar la política antes de ser bloqueado a /perfil/seguridad
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'requiere_2fa')) {
                $t->boolean('requiere_2fa')->default(false)->after('suspendido_at');
            }
            if (!Schema::hasColumn('tenants', 'requiere_2fa_desde')) {
                $t->timestamp('requiere_2fa_desde')->nullable()->after('requiere_2fa');
            }
            if (!Schema::hasColumn('tenants', 'gracia_2fa_dias')) {
                $t->unsignedSmallInteger('gracia_2fa_dias')->default(3)->after('requiere_2fa_desde');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['requiere_2fa', 'requiere_2fa_desde', 'gracia_2fa_dias'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });
    }
};
