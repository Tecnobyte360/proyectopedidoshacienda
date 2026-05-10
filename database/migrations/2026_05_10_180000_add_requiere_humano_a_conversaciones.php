<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'requiere_humano')) {
                $table->boolean('requiere_humano')->default(false)->after('estado')
                    ->comment('Bot decidió escalar a humano');
            }
            if (!Schema::hasColumn('conversaciones_whatsapp', 'humano_motivo')) {
                $table->string('humano_motivo', 200)->nullable()->after('requiere_humano')
                    ->comment('Razón del handoff: frustracion_cliente | bot_error | etc');
            }
            if (!Schema::hasColumn('conversaciones_whatsapp', 'humano_solicitado_at')) {
                $table->timestamp('humano_solicitado_at')->nullable()->after('humano_motivo');
            }
            if (!Schema::hasColumn('conversaciones_whatsapp', 'humano_atendido_at')) {
                $table->timestamp('humano_atendido_at')->nullable()->after('humano_solicitado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            foreach (['requiere_humano', 'humano_motivo', 'humano_solicitado_at', 'humano_atendido_at'] as $col) {
                if (Schema::hasColumn('conversaciones_whatsapp', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
