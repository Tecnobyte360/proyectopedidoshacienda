<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 💳 Campos Wompi en `pagos` (tabla SaaS — cobro Kivox→tenants).
 *
 * NO confundir con wompi_reference de `pedidos` (cobro tenant→cliente final).
 * Esta es para que el dueño Kivox cobre las mensualidades a las empresas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            if (!Schema::hasColumn('pagos', 'wompi_reference')) {
                $t->string('wompi_reference', 100)->nullable()->unique()->after('referencia');
            }
            if (!Schema::hasColumn('pagos', 'wompi_transaction_id')) {
                $t->string('wompi_transaction_id', 100)->nullable()->after('wompi_reference');
            }
            if (!Schema::hasColumn('pagos', 'wompi_status')) {
                $t->string('wompi_status', 32)->nullable()->after('wompi_transaction_id');
            }
            if (!Schema::hasColumn('pagos', 'link_pago_url')) {
                $t->text('link_pago_url')->nullable()->after('wompi_status');
            }
            if (!Schema::hasColumn('pagos', 'link_pago_generado_at')) {
                $t->timestamp('link_pago_generado_at')->nullable()->after('link_pago_url');
            }
            if (!Schema::hasColumn('pagos', 'link_enviado_at')) {
                $t->timestamp('link_enviado_at')->nullable()->after('link_pago_generado_at');
            }
            if (!Schema::hasColumn('pagos', 'link_canal_envio')) {
                $t->string('link_canal_envio', 24)->nullable()->after('link_enviado_at'); // whatsapp|email|copy
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            foreach (['wompi_reference','wompi_transaction_id','wompi_status','link_pago_url','link_pago_generado_at','link_enviado_at','link_canal_envio'] as $col) {
                if (Schema::hasColumn('pagos', $col)) $t->dropColumn($col);
            }
        });
    }
};
