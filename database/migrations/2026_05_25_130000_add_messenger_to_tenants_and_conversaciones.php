<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 💬 Facebook Messenger DMs
 *
 * Reusa la infraestructura de Instagram:
 * - El instagram_page_id ES el Facebook Page ID (cuando IG está vinculada a FB Page)
 * - El instagram_access_token con scope pages_messaging sirve para Messenger también
 * - Nueva conversaciones_whatsapp.canal = 'messenger'
 *
 * Solo añadimos: flag messenger_activo + page token específico (opcional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (!Schema::hasColumn('tenants', 'messenger_activo')) {
                $t->boolean('messenger_activo')->default(false);
            }
            // Page Access Token específico para Messenger (opcional — si está vacío usa el de IG)
            if (!Schema::hasColumn('tenants', 'messenger_page_access_token')) {
                $t->text('messenger_page_access_token')->nullable();
            }
        });

        // Para Messenger usamos PSID (Page-Scoped User ID) — diferente al IGSID
        Schema::table('conversaciones_whatsapp', function (Blueprint $t) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'psid')) {
                $t->string('psid', 60)->nullable()->after('igsid')
                  ->comment('Page-Scoped ID de Messenger');
                $t->index('psid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            foreach (['messenger_activo','messenger_page_access_token'] as $c) {
                if (Schema::hasColumn('tenants', $c)) $t->dropColumn($c);
            }
        });

        Schema::table('conversaciones_whatsapp', function (Blueprint $t) {
            if (Schema::hasColumn('conversaciones_whatsapp', 'psid')) {
                $t->dropIndex(['psid']);
                $t->dropColumn('psid');
            }
        });
    }
};
