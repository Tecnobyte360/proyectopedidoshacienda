<?php

namespace App\Console\Commands;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🧹 LIBERA CONVERSACIONES HUÉRFANAS
 *
 * Conversaciones que están en modo humano pero ningún operador
 * ha respondido en MÁS DE N HORAS. Las libera (atendida_por_humano=0,
 * requiere_humano=0, derivada_at=null) para que el bot pueda retomar
 * cuando el cliente vuelva a escribir.
 *
 * Se programa cada 30 min en routes/console.php.
 *
 * Usar --horas para cambiar el umbral (default 2).
 * Usar --dry-run para ver qué conversaciones tocaría sin liberar.
 */
class LiberarConversacionesHuerfanas extends Command
{
    protected $signature = 'bot:liberar-conversaciones-huerfanas
                            {--horas=2 : Horas desde la derivación sin respuesta humana}
                            {--dry-run : Mostrar conversaciones a liberar sin tocarlas}';

    protected $description = 'Libera conversaciones colgadas en modo humano que llevan más de N horas sin atención';

    public function handle(): int
    {
        $horas  = max(1, (int) $this->option('horas'));
        $dryRun = (bool) $this->option('dry-run');

        $umbral = now()->subHours($horas);

        $conversaciones = ConversacionWhatsapp::withoutGlobalScopes()
            ->where('atendida_por_humano', true)
            ->where(function ($q) use ($umbral) {
                $q->where('derivada_at', '<=', $umbral)
                  ->orWhere(function ($qq) use ($umbral) {
                      $qq->whereNull('derivada_at')->where('updated_at', '<=', $umbral);
                  });
            })
            ->get();

        if ($conversaciones->isEmpty()) {
            $this->info("✓ Sin conversaciones huérfanas (umbral: {$horas}h).");
            return self::SUCCESS;
        }

        $liberadas = 0;
        foreach ($conversaciones as $c) {
            $referencia = $c->derivada_at ?: $c->updated_at;

            // ¿Hubo respuesta de operador desde la derivación?
            $hayMensajeHumano = MensajeWhatsapp::where('conversacion_id', $c->id)
                ->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
                ->where('created_at', '>', $referencia)
                ->whereJsonContains('meta->origen', 'operador')
                ->exists();

            if ($hayMensajeHumano) {
                // El humano atendió → no liberar
                continue;
            }

            $this->line("  Conv #{$c->id} (tenant {$c->tenant_id}, tel {$c->telefono_normalizado}) — derivada hace ~"
                . round(now()->diffInMinutes($referencia) / 60, 1) . "h");

            if (!$dryRun) {
                $c->update([
                    'atendida_por_humano' => false,
                    'requiere_humano'     => false,
                    'humano_motivo'       => null,
                    'departamento_id'     => null,
                    'derivada_at'         => null,
                ]);
                Log::info('🧹 Conversación huérfana liberada', [
                    'conv_id'    => $c->id,
                    'tenant_id'  => $c->tenant_id,
                    'horas_huerfana' => round(now()->diffInMinutes($referencia) / 60, 1),
                ]);
            }
            $liberadas++;
        }

        if ($dryRun) {
            $this->warn("[dry-run] Se liberarían {$liberadas} conversación(es).");
        } else {
            $this->info("✓ Liberadas {$liberadas} conversación(es) huérfana(s).");
        }

        return self::SUCCESS;
    }
}
