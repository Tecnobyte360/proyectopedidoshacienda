<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            // 🧹 Auto-limpieza diaria del historial WhatsApp
            $table->boolean('auto_limpieza_activa')->default(true)
                ->after('flujo_pedido_orden');
            $table->string('auto_limpieza_hora', 5)->default('03:30')
                ->after('auto_limpieza_activa')
                ->comment('Hora HH:MM en que corre la limpieza diaria (zona Bogotá)');
            $table->integer('auto_limpieza_dias')->default(7)
                ->after('auto_limpieza_hora')
                ->comment('Borrar mensajes más antiguos que N días');
            $table->integer('auto_limpieza_max_msgs')->default(100)
                ->after('auto_limpieza_dias')
                ->comment('Mantener máximo N mensajes recientes por conversación');

            // ⏰ Auto-reset al saludar después de N horas de inactividad
            $table->integer('auto_reset_horas_inactividad')->default(3)
                ->after('auto_limpieza_max_msgs')
                ->comment('Resetear contexto si el cliente saluda y han pasado N horas sin actividad');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_bot', function (Blueprint $table) {
            $table->dropColumn([
                'auto_limpieza_activa',
                'auto_limpieza_hora',
                'auto_limpieza_dias',
                'auto_limpieza_max_msgs',
                'auto_reset_horas_inactividad',
            ]);
        });
    }
};
