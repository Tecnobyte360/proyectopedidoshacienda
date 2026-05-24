<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanas_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('campanas_whatsapp', 'plantilla_meta_nombre')) {
                $table->string('plantilla_meta_nombre', 120)->nullable()->after('media_url');
            }
            if (!Schema::hasColumn('campanas_whatsapp', 'plantilla_meta_idioma')) {
                $table->string('plantilla_meta_idioma', 10)->nullable()->after('plantilla_meta_nombre');
            }
            if (!Schema::hasColumn('campanas_whatsapp', 'plantilla_meta_variables')) {
                $table->json('plantilla_meta_variables')->nullable()->after('plantilla_meta_idioma');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campanas_whatsapp', function (Blueprint $table) {
            foreach (['plantilla_meta_variables', 'plantilla_meta_idioma', 'plantilla_meta_nombre'] as $col) {
                if (Schema::hasColumn('campanas_whatsapp', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
