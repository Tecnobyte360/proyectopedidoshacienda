<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // 🔐 Bandera individual para forzar 2FA a un usuario específico
            // (independiente de la política del tenant). El admin del tenant la
            // marca; el middleware Forzar2FA redirige al usuario a
            // /perfil/seguridad hasta que active 2FA.
            if (!Schema::hasColumn('users', 'requiere_2fa')) {
                $t->boolean('requiere_2fa')->default(false)->after('two_factor_enabled_at');
            }
            if (!Schema::hasColumn('users', 'requiere_2fa_desde')) {
                $t->timestamp('requiere_2fa_desde')->nullable()->after('requiere_2fa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'requiere_2fa_desde')) $t->dropColumn('requiere_2fa_desde');
            if (Schema::hasColumn('users', 'requiere_2fa'))       $t->dropColumn('requiere_2fa');
        });
    }
};
