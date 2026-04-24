<?php

namespace App\Console\Commands;

use App\Models\MensajeWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpia mensajes duplicados en conversaciones_whatsapp:
 *   - Mismo mensaje_externo_id → deja el más antiguo, borra los demás.
 *   - Mismo conversacion_id + rol + contenido + created_at(±3s) → deja el más antiguo.
 */
class LimpiarMensajesDuplicados extends Command
{
    protected $signature = 'mensajes:limpiar-duplicados {--dry-run}';
    protected $description = 'Elimina mensajes duplicados en el historial de chat.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $total = 0;

        // 1) Por mensaje_externo_id (más confiable)
        $grupos1 = DB::table('mensajes_whatsapp')
            ->select('mensaje_externo_id', DB::raw('COUNT(*) as n'))
            ->whereNotNull('mensaje_externo_id')
            ->groupBy('mensaje_externo_id')
            ->having('n', '>', 1)
            ->get();

        foreach ($grupos1 as $g) {
            $ids = MensajeWhatsapp::where('mensaje_externo_id', $g->mensaje_externo_id)
                ->orderBy('id')
                ->pluck('id')
                ->all();
            array_shift($ids); // deja el primero
            if (empty($ids)) continue;
            $this->line("  #{$g->mensaje_externo_id} → eliminar " . count($ids));
            if (!$dry) MensajeWhatsapp::whereIn('id', $ids)->delete();
            $total += count($ids);
        }

        // 2) Mismo conversacion_id + rol + contenido en < 5 segundos
        $grupos2 = DB::table('mensajes_whatsapp')
            ->select('conversacion_id', 'rol', 'contenido', DB::raw('COUNT(*) as n'))
            ->whereNull('mensaje_externo_id')
            ->groupBy('conversacion_id', 'rol', 'contenido')
            ->having('n', '>', 1)
            ->get();

        foreach ($grupos2 as $g) {
            $mensajes = MensajeWhatsapp::where('conversacion_id', $g->conversacion_id)
                ->where('rol', $g->rol)
                ->where('contenido', $g->contenido)
                ->orderBy('created_at')
                ->get();

            $ultimoTs = null;
            $aBorrar = [];
            foreach ($mensajes as $m) {
                if ($ultimoTs && $m->created_at->diffInSeconds($ultimoTs) <= 5) {
                    $aBorrar[] = $m->id;
                } else {
                    $ultimoTs = $m->created_at;
                }
            }
            if (empty($aBorrar)) continue;
            $this->line("  conv {$g->conversacion_id} / {$g->rol} → eliminar " . count($aBorrar));
            if (!$dry) MensajeWhatsapp::whereIn('id', $aBorrar)->delete();
            $total += count($aBorrar);
        }

        $this->info("✅ Eliminados: {$total} mensaje(s) duplicados.");
        if ($dry) $this->warn('(DRY-RUN — no se escribió nada)');

        return self::SUCCESS;
    }
}
