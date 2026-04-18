<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('felicitaciones_cumpleanos', function (Blueprint $table) {
            if (!Schema::hasColumn('felicitaciones_cumpleanos', 'connection_id')) {
                $table->unsignedBigInteger('connection_id')->nullable()->after('telefono')->index();
            }
        });

        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // Conexión por defecto para envíos salientes automáticos
            // (cumpleaños, promos, etc) cuando el cliente no tiene conversación previa.
            if (!Schema::hasColumn('configuraciones_bot', 'connection_id_default')) {
                $table->unsignedBigInteger('connection_id_default')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('felicitaciones_cumpleanos', function (Blueprint $table) {
            $table->dropColumn('connection_id');
        });
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn('connection_id_default');
        });
    }
};
