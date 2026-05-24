<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔐 Two-Factor Authentication (TOTP - RFC 6238)
 *
 * - two_factor_secret: secreto Base32 (16 chars) — encrypted at rest
 * - two_factor_recovery_codes: 8 códigos single-use JSON — encrypted at rest
 * - two_factor_enabled_at: timestamp cuando se confirmó la activación
 *   (NULL = en proceso de activación o desactivado)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $t->text('two_factor_secret')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $t->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (!Schema::hasColumn('users', 'two_factor_enabled_at')) {
                $t->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_recovery_codes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled_at'] as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
    }
};
