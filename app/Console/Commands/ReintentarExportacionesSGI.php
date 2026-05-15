<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\IntegracionExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 Reintenta exportar pedidos al ERP/SGI que fallaron por errores transitorios
 * (DBPROCESS is dead, connection timeout, etc).
 *
 * Lógica:
 *  - Toma pedidos con último log de export en estado=error de los últimos 24h
 *  - Si NO tienen un log de export exitoso después del error → reintentar
 *  - Si el reintento tiene éxito → log nuevo OK
 *  - Si vuelve a fallar → log de error nuevo (con contador acumulado)
 *
 * Schedule: ejecutar cada 5 minutos vía Kernel.php
 *
 * Uso manual:
 *   php artisan integraciones:reintentar-export        → reintenta pedidos en error
 *   php artisan integraciones:reintentar-export --id=6 → reintenta solo el pedido #6
 */
class ReintentarExportacionesSGI extends Command
{
    protected $signature = 'integraciones:reintentar-export
                            {--id= : Reintentar solo el pedido con este ID}
                            {--max=20 : Máximo de pedidos a procesar en este ciclo}
                            {--horas=24 : Solo pedidos con error en las últimas N horas}';

    protected $description = 'Reintenta exportar pedidos al ERP que fallaron por errores transitorios';

    public function handle(IntegracionExportService $exportService): int
    {
        $idFiltro = $this->option('id');
        $max      = (int) $this->option('max');
        $horas    = (int) $this->option('horas');

        // Obtener IDs únicos de pedidos con ÚLTIMO log = error
        $query = DB::table('integracion_export_logs as l1')
            ->select('l1.pedido_id', 'l1.tenant_id', 'l1.error_mensaje', 'l1.created_at')
            ->where('l1.estado', 'error')
            ->where('l1.created_at', '>=', now()->subHours($horas))
            // No hay log posterior exitoso para este pedido
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('integracion_export_logs as l2')
                  ->whereColumn('l2.pedido_id', 'l1.pedido_id')
                  ->whereColumn('l2.integracion_id', 'l1.integracion_id')
                  ->where('l2.estado', 'ok')
                  ->whereColumn('l2.created_at', '>', 'l1.created_at');
            })
            ->orderByDesc('l1.created_at');

        if ($idFiltro) {
            $query->where('l1.pedido_id', (int) $idFiltro);
        }

        $pendientes = $query->limit($max)->get()->unique('pedido_id');

        if ($pendientes->isEmpty()) {
            $this->info('No hay pedidos en error pendientes de reintento.');
            return self::SUCCESS;
        }

        $this->info("🔄 Reintentando export de {$pendientes->count()} pedido(s)...");

        $exitosos = 0;
        $fallidos = 0;

        foreach ($pendientes as $row) {
            $pedido = Pedido::find($row->pedido_id);
            if (!$pedido) {
                $this->warn("  ⚠️  Pedido #{$row->pedido_id} no encontrado (eliminado?)");
                continue;
            }

            $this->line("  → Reintentando pedido #{$pedido->id}...");
            try {
                $r = $exportService->exportarPedido($pedido);
                $hayOk = collect($r['resultados'] ?? [])
                    ->filter(fn ($x) => ($x['estado'] ?? '') === 'ok')
                    ->isNotEmpty();

                if ($hayOk) {
                    $this->info("    ✅ Pedido #{$pedido->id} exportado exitosamente");
                    $exitosos++;
                } else {
                    $this->warn("    ❌ Pedido #{$pedido->id} sigue fallando");
                    $fallidos++;
                }
            } catch (\Throwable $e) {
                $this->error("    💥 Excepción en pedido #{$pedido->id}: " . $e->getMessage());
                Log::warning('ReintentarExportacionesSGI: excepción procesando pedido', [
                    'pedido_id' => $pedido->id,
                    'error'     => $e->getMessage(),
                ]);
                $fallidos++;
            }
        }

        $this->newLine();
        $this->info("Resumen: ✅ {$exitosos} exitosos | ❌ {$fallidos} fallidos");

        return self::SUCCESS;
    }
}
