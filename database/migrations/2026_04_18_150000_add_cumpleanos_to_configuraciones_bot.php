<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_activo')) {
                $table->boolean('cumpleanos_activo')->default(true);
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_hora')) {
                // HH:MM, hora a la que se dispara la felicitación
                $table->string('cumpleanos_hora', 5)->default('09:00');
            }
            if (!Schema::hasColumn('configuraciones_bot', 'cumpleanos_mensaje')) {
                $table->text('cumpleanos_mensaje')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn(['cumpleanos_activo', 'cumpleanos_hora', 'cumpleanos_mensaje']);
        });
    }
};
