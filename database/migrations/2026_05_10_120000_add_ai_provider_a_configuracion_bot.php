<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'ai_provider')) {
                $table->string('ai_provider', 32)->default('openai')->after('modelo_openai')
                    ->comment('Proveedor de IA: openai | anthropic');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'modelo_anthropic')) {
                $table->string('modelo_anthropic', 64)->nullable()->after('ai_provider')
                    ->comment('Modelo Claude (claude-sonnet-4-6, etc.)');
            }
        });

        // Tenants tabla — agregar campo de API key Anthropic si no existe
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'anthropic_api_key')) {
                $table->text('anthropic_api_key')->nullable()->after('openai_api_key')
                    ->comment('API key de Anthropic (encriptada)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_bot', 'ai_provider')) {
                $table->dropColumn('ai_provider');
            }
            if (Schema::hasColumn('configuraciones_bot', 'modelo_anthropic')) {
                $table->dropColumn('modelo_anthropic');
            }
        });
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'anthropic_api_key')) {
                $table->dropColumn('anthropic_api_key');
            }
        });
    }
};
