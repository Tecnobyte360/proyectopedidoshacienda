<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Número Meta (phone_number_id) que atiende esta sede. Los pedidos del
            // bot que entran por 'meta:{phone_number_id}' se asignan a esta sede.
            $table->string('meta_phone_number_id')->nullable()->after('whatsapp_connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn('meta_phone_number_id');
        });
    }
};
