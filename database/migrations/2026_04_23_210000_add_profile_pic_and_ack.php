<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'profile_pic_url')) {
                $table->string('profile_pic_url', 500)->nullable()->after('nombre');
            }
        });

        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('mensajes_whatsapp', 'ack')) {
                // 0=pending, 1=sent, 2=delivered, 3=read, 4=played
                $table->tinyInteger('ack')->default(0)->after('rol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'profile_pic_url')) {
                $table->dropColumn('profile_pic_url');
            }
        });

        Schema::table('mensajes_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('mensajes_whatsapp', 'ack')) {
                $table->dropColumn('ack');
            }
        });
    }
};
