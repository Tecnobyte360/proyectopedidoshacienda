<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 📸 FOTO DE PERFIL DEL CLIENTE
 *
 * Agrega columnas para guardar el avatar de WhatsApp del cliente:
 *   - foto_url: ruta relativa al storage local (/storage/tenants/.../avatars/X.jpg)
 *   - foto_actualizada_at: cuándo se descargó (para re-sincronizar c/N días)
 *   - foto_origen: WA | manual | fallback
 *
 * NO guardamos la URL directa de WhatsApp porque caduca cada cierto tiempo.
 * Descargamos la imagen y la servimos desde nuestro storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'foto_url')) {
                $table->string('foto_url', 500)->nullable()->after('email');
            }
            if (!Schema::hasColumn('clientes', 'foto_actualizada_at')) {
                $table->timestamp('foto_actualizada_at')->nullable()->after('foto_url');
            }
            if (!Schema::hasColumn('clientes', 'foto_origen')) {
                $table->string('foto_origen', 20)->default('wa')->after('foto_actualizada_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['foto_url', 'foto_actualizada_at', 'foto_origen']);
        });
    }
};
