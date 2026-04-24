<?php

namespace App\Console\Commands;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona conversaciones duplicadas por teléfono dentro del mismo tenant.
 * Mueve todos los mensajes a la conversación más antigua y elimina (soft)
 * las demás. Recalcula contadores y deja esa única fila como activa.
 */
class FusionarConversacionesDuplicadas extends Command
{
    protected $signature = 'chats:fusionar-duplicadas {--dry-run : Muestra qué haría sin escribir en BD}';

    protected $description = 'Fusiona conversaciones duplicadas del mismo teléfono en una sola por tenant';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY-RUN: no se escribirá nada en BD');
        }

        // Ignoramos el scope de tenant para procesar todos
        $grupos = ConversacionWhatsapp::withoutGlobalScopes()
            ->select('tenant_id', 'telefono_normalizado', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->where('estado', '!=', ConversacionWhatsapp::ESTADO_ARCHIVADA)
            ->groupBy('tenant_id', 'telefono_normalizado')
            ->having('total', '>', 1)
            ->get();

        if ($grupos->isEmpty()) {
            $this->info('✓ No hay duplicadas.');
            return self::SUCCESS;
        }

        $this->info("Encontrados {$grupos->count()} grupos con duplicados.");

        $totalFusionadas = 0;

        foreach ($grupos as $g) {
            $convs = ConversacionWhatsapp::withoutGlobalScopes()
                ->where('tenant_id', $g->tenant_id)
                ->where('telefono_normalizado', $g->telefono_normalizado)
                ->where('estado', '!=', ConversacionWhatsapp::ESTADO_ARCHIVADA)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            if ($convs->count() < 2) continue;

            $principal = $convs->first();
            $duplicadas = $convs->slice(1);

            $this->line(sprintf(
                '📞 tenant %s — %s → principal #%d, fusionando %d',
                $g->tenant_id ?? 'null',
                $g->telefono_normalizado,
                $principal->id,
                $duplicadas->count(),
            ));

            if ($dryRun) {
                $totalFusionadas += $duplicadas->count();
                continue;
            }

            DB::transaction(function () use ($principal, $duplicadas) {
                $ids = $duplicadas->pluck('id')->all();

                // Mover todos los mensajes a la principal
                MensajeWhatsapp::whereIn('conversacion_id', $ids)
                    ->update(['conversacion_id' => $principal->id]);

                // Soft-delete de las duplicadas
                ConversacionWhatsapp::withoutGlobalScopes()
                    ->whereIn('id', $ids)
                    ->delete();

                // Recalcular contadores y fechas de la principal
                $stats = MensajeWhatsapp::where('conversacion_id', $principal->id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN rol = "cliente" THEN 1 ELSE 0 END) as total_cli,
                        SUM(CASE WHEN rol = "bot" THEN 1 ELSE 0 END) as total_bot,
                        MIN(created_at) as primer_at,
                        MAX(created_at) as ultimo_at
                    ')
                    ->first();

                $principal->update([
                    'estado'                 => ConversacionWhatsapp::ESTADO_ACTIVA,
                    'total_mensajes'         => (int) $stats->total,
                    'total_mensajes_cliente' => (int) $stats->total_cli,
                    'total_mensajes_bot'     => (int) $stats->total_bot,
                    'primer_mensaje_at'      => $stats->primer_at,
                    'ultimo_mensaje_at'      => $stats->ultimo_at,
                ]);
            });

            $totalFusionadas += $duplicadas->count();
        }

        $this->info("✅ Listo. Conversaciones fusionadas: {$totalFusionadas}");
        return self::SUCCESS;
    }
}
