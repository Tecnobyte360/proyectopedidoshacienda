<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 🛵 Agrega 'en_ruta' al ENUM `domiciliarios.estado`.
 *
 * El modelo Domiciliario::ESTADO_EN_RUTA = 'en_ruta' existe en código pero
 * el ENUM en BD solo tenía ('disponible','ocupado','inactivo'). Esto causaba
 * que la auto-asignación fallara con "Data truncated for column 'estado'".
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE domiciliarios MODIFY COLUMN estado ENUM('disponible','en_ruta','ocupado','inactivo') NOT NULL DEFAULT 'disponible'");
    }

    public function down(): void
    {
        // Antes de revertir, normalizar cualquier 'en_ruta' existente
        DB::table('domiciliarios')->where('estado', 'en_ruta')->update(['estado' => 'ocupado']);
        DB::statement("ALTER TABLE domiciliarios MODIFY COLUMN estado ENUM('disponible','ocupado','inactivo') NOT NULL DEFAULT 'disponible'");
    }
};
