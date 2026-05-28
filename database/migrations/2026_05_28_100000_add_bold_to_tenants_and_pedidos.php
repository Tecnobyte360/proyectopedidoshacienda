<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 💳 Bold — segunda pasarela de pago opcional por tenant.
 *
 * Cada tenant elige: Wompi, Bold o ambas (cliente final decide al pagar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'bold_activo')) {
                $t->boolean('bold_activo')->default(false);
            }
            if (!Schema::hasColumn('tenants', 'bold_modo')) {
                $t->string('bold_modo', 20)->default('test')->comment('test|production');
            }
            if (!Schema::hasColumn('tenants', 'bold_api_key')) {
                $t->text('bold_api_key')->nullable()->comment('encriptada');
            }
            if (!Schema::hasColumn('tenants', 'bold_secret_key')) {
                $t->text('bold_secret_key')->nullable()->comment('encriptada — para firmar webhooks');
            }
            if (!Schema::hasColumn('tenants', 'bold_webhook_secret')) {
                $t->text('bold_webhook_secret')->nullable()->comment('encriptado — verificar firma webhooks');
            }
            // Selector preferido cuando hay dos pasarelas activas
            if (!Schema::hasColumn('tenants', 'pasarela_preferida')) {
                $t->string('pasarela_preferida', 20)->default('cliente_elige')
                  ->comment('wompi|bold|cliente_elige');
            }
        });

        Schema::table('pedidos', function (Blueprint $t) {
            if (!Schema::hasColumn('pedidos', 'bold_payment_link')) {
                $t->string('bold_payment_link', 500)->nullable();
            }
            if (!Schema::hasColumn('pedidos', 'bold_payment_id')) {
                $t->string('bold_payment_id', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('pedidos', 'bold_transaction_id')) {
                $t->string('bold_transaction_id', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('pedidos', 'pasarela_usada')) {
                $t->string('pasarela_usada', 20)->nullable()->comment('wompi|bold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['bold_activo','bold_modo','bold_api_key','bold_secret_key','bold_webhook_secret','pasarela_preferida'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('pedidos', function (Blueprint $t) {
            foreach (['bold_payment_link','bold_payment_id','bold_transaction_id','pasarela_usada'] as $c) {
                if (Schema::hasColumn('pedidos', $c)) $t->dropColumn($c);
            }
        });
    }
};
