<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            if (!Schema::hasColumn('sedes', 'whatsapp_connection_id')) {
                $table->unsignedBigInteger('whatsapp_connection_id')->nullable()->after('mensaje_cerrado');
            }
            if (!Schema::hasColumn('sedes', 'whatsapp_id')) {
                $table->unsignedBigInteger('whatsapp_id')->nullable()->after('whatsapp_connection_id');
            }
            if (!Schema::hasColumn('sedes', 'whatsapp_telefono')) {
                $table->string('whatsapp_telefono', 32)->nullable()->after('whatsapp_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            foreach (['whatsapp_connection_id', 'whatsapp_id', 'whatsapp_telefono'] as $col) {
                if (Schema::hasColumn('sedes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
