<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 📷 Instagram messaging integration
 *
 * Permite a Kivox recibir y responder DMs de Instagram en el mismo inbox
 * que WhatsApp. Misma estructura de conversaciones — agregamos columna
 * 'canal' para distinguir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Distinguir canal en conversaciones
        Schema::table('conversaciones_whatsapp', function (Blueprint $t) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'canal')) {
                $t->string('canal', 20)->default('whatsapp')->after('id')
                  ->comment('whatsapp | instagram | messenger');
                $t->index('canal');
            }
            // ID externo del usuario en IG (IGSID) — diferente al phone de WA
            if (!Schema::hasColumn('conversaciones_whatsapp', 'igsid')) {
                $t->string('igsid', 60)->nullable()->after('telefono_normalizado')
                  ->comment('Instagram-Scoped ID del usuario para IG DMs');
                $t->index('igsid');
            }
        });

        // 2. Tenant config para Instagram
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'instagram_business_account_id')) {
                $t->string('instagram_business_account_id', 60)->nullable()->after('meta_phone_number_id');
            }
            if (!Schema::hasColumn('tenants', 'instagram_page_id')) {
                $t->string('instagram_page_id', 60)->nullable()->after('instagram_business_account_id');
            }
            if (!Schema::hasColumn('tenants', 'instagram_activo')) {
                $t->boolean('instagram_activo')->default(false)->after('instagram_page_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $t) {
            if (Schema::hasColumn('conversaciones_whatsapp', 'igsid')) {
                $t->dropIndex(['igsid']);
                $t->dropColumn('igsid');
            }
            if (Schema::hasColumn('conversaciones_whatsapp', 'canal')) {
                $t->dropIndex(['canal']);
                $t->dropColumn('canal');
            }
        });

        Schema::table('tenants', function (Blueprint $t) {
            foreach (['instagram_business_account_id', 'instagram_page_id', 'instagram_activo'] as $col) {
                if (Schema::hasColumn('tenants', $col)) $t->dropColumn($col);
            }
        });
    }
};
