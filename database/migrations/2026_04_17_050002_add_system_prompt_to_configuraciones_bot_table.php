<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->boolean('usar_prompt_personalizado')->default(false)->after('frase_bienvenida');
            $table->text('system_prompt')->nullable()->after('usar_prompt_personalizado');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn(['usar_prompt_personalizado', 'system_prompt']);
        });
    }
};
