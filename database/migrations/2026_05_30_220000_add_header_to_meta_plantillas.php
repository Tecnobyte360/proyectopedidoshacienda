<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meta_whatsapp_plantillas', function (Blueprint $t) {
            // none | text | image | video | document
            $t->string('header_tipo', 16)->nullable()->after('footer');
            // Texto del header si tipo=text (puede tener {{1}})
            $t->string('header_texto', 200)->nullable()->after('header_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_plantillas', function (Blueprint $t) {
            $t->dropColumn(['header_tipo', 'header_texto']);
        });
    }
};
