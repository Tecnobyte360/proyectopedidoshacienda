<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Estructura JSON con horarios por día:
            // {
            //   "lunes":     {"abierto": true,  "abre": "08:00", "cierra": "18:00"},
            //   "martes":    {"abierto": true,  "abre": "08:00", "cierra": "18:00"},
            //   "miercoles": {"abierto": true,  "abre": "08:00", "cierra": "18:00"},
            //   "jueves":    {"abierto": true,  "abre": "08:00", "cierra": "18:00"},
            //   "viernes":   {"abierto": true,  "abre": "08:00", "cierra": "18:00"},
            //   "sabado":    {"abierto": true,  "abre": "09:00", "cierra": "16:00"},
            //   "domingo":   {"abierto": false, "abre": null,    "cierra": null}
            // }
            if (!Schema::hasColumn('sedes', 'horarios')) {
                $table->json('horarios')->nullable();
            }

            // Mensaje opcional cuando está cerrado (para mostrar en bot)
            if (!Schema::hasColumn('sedes', 'mensaje_cerrado')) {
                $table->text('mensaje_cerrado')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn(['horarios', 'mensaje_cerrado']);
        });
    }
};
