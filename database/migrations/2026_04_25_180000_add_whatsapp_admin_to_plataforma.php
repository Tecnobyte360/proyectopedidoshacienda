<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $table) {
            if (!Schema::hasColumn('configuracion_plataforma', 'whatsapp_admin_email')) {
                $table->string('whatsapp_admin_email', 120)->nullable()->after('sitio_web');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'whatsapp_admin_password')) {
                $table->string('whatsapp_admin_password', 200)->nullable()->after('whatsapp_admin_email');
            }
            if (!Schema::hasColumn('configuracion_plataforma', 'whatsapp_api_base_url')) {
                $table->string('whatsapp_api_base_url', 200)
                    ->default('https://wa-api.tecnobyteapp.com:1422')
                    ->after('whatsapp_admin_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_plataforma', function (Blueprint $table) {
            $cols = ['whatsapp_admin_email', 'whatsapp_admin_password', 'whatsapp_api_base_url'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('configuracion_plataforma', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
