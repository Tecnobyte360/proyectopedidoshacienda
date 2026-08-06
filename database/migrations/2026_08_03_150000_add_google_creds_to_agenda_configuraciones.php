<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales OAuth de Google POR TENANT (cada empresa pone las suyas desde su
 * panel). Antes se leían del .env global; ahora se configuran por interfaz.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agenda_configuraciones', function (Blueprint $t) {
            $t->string('google_client_id')->nullable()->after('google_conectado');
            $t->text('google_client_secret')->nullable()->after('google_client_id'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('agenda_configuraciones', function (Blueprint $t) {
            $t->dropColumn(['google_client_id', 'google_client_secret']);
        });
    }
};
