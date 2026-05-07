<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Limpia historial viejo de conversaciones del bot para evitar:
 *  - Acumulación de mensajes que infla el contexto enviado a OpenAI
 *  - Rate limits 429 ("Request too large")
 *  - Crecimiento descontrolado de mensajes_whatsapp y cache
 *
 * Ejecutar diariamente:
 *   $schedule->command('bot:limpiar-historial')->dailyAt('03:00');
 */
class BotLimpiarHistorial extends Command
{
    protected $signature = 'bot:limpiar-historial
                          {--dias=7 : Borrar mensajes más viejos que N días}
                          {--max-msgs-por-conv=100 : Mantener máximo N mensajes recientes por conversación}
                          {--dry : Solo simular sin borrar}';

    protected $description = 'Limpia mensajes WhatsApp viejos para mantener el historial liviano';

    public function handle(): int
    {
        $dias        = (int) $this->option('dias');
        $maxPorConv  = (int) $this->option('max-msgs-por-conv');
        $dry         = (bool) $this->option('dry');

        $this->info("🧹 Limpieza de historial WhatsApp" . ($dry ? ' (DRY-RUN)' : ''));
        $this->info("   • Borrar mensajes >$dias días");
        $this->info("   • Mantener máx {$maxPorConv} mensajes recientes por conversación");
        $this->newLine();

        // 1. Mensajes muy viejos (>N días)
        $q = DB::table('mensajes_whatsapp')->where('created_at', '<', now()->subDays($dias));
        $countViejos = $q->count();
        $this->line("📅 Mensajes >$dias días: {$countViejos}");
        if (!$dry && $countViejos > 0) {
            $q->delete();
            $this->info("   ✓ Borrados");
        }

        // 2. Conversaciones con muchos mensajes — trim al top N
        $convsGrandes = DB::table('mensajes_whatsapp')
            ->select('conversacion_id', DB::raw('COUNT(*) as total'))
            ->groupBy('conversacion_id')
            ->having('total', '>', $maxPorConv)
            ->get();

        $totalTrimmed = 0;
        foreach ($convsGrandes as $cg) {
            // Encontrar el ID a partir del cual borramos (los más viejos)
            $idCorte = DB::table('mensajes_whatsapp')
                ->where('conversacion_id', $cg->conversacion_id)
                ->orderByDesc('id')
                ->skip($maxPorConv - 1)
                ->take(1)
                ->value('id');

            if ($idCorte) {
                $borrados = DB::table('mensajes_whatsapp')
                    ->where('conversacion_id', $cg->conversacion_id)
                    ->where('id', '<', $idCorte)
                    ->{$dry ? 'count' : 'delete'}();
                $totalTrimmed += $borrados;
                $this->line("   • Conv #{$cg->conversacion_id}: {$cg->total} → {$maxPorConv} ({$borrados} borrados)");
            }
        }
        $this->info("✂️ Total trimmed por exceso: {$totalTrimmed}");

        // 3. Recalcular contadores en conversaciones_whatsapp
        if (!$dry) {
            DB::statement("
                UPDATE conversaciones_whatsapp c
                SET total_mensajes = (
                    SELECT COUNT(*) FROM mensajes_whatsapp WHERE conversacion_id = c.id
                )
            ");
            $this->info("📊 Contadores recalculados");
        }

        // 4. Limpiar cache antiguo
        if (!$dry) {
            $cacheBorrado = DB::table('cache')
                ->where('expiration', '<', now()->timestamp)
                ->delete();
            $this->info("🗑️ Cache expirado: {$cacheBorrado}");
        }

        // 5. Resumen
        $this->newLine();
        $totalActual = DB::table('mensajes_whatsapp')->count();
        $bytesActual = DB::selectOne("SELECT SUM(LENGTH(contenido) + COALESCE(LENGTH(metadata),0)) as b FROM mensajes_whatsapp")?->b ?? 0;
        $this->line("📦 Total ahora: {$totalActual} mensajes (" . round($bytesActual / 1024) . " KB)");

        Log::info('🧹 bot:limpiar-historial ejecutado', [
            'mensajes_viejos_borrados' => $countViejos,
            'mensajes_trimmed' => $totalTrimmed,
            'total_restante' => $totalActual,
            'dry_run' => $dry,
        ]);

        return self::SUCCESS;
    }
}
