<?php

namespace App\Console\Commands;

use App\Services\ErpRetryQueueService;
use Illuminate\Console\Command;

/**
 * 🔄 ERP PROCESAR COLA
 *
 * Reintenta sincronizaciones pendientes con ERP que fallaron porque
 * el SQL Server / API del ERP estaba caído. Corre cada 5 min vía
 * scheduler. También puede invocarse manualmente:
 *
 *   docker exec chatpedidos_app php artisan erp:procesar-cola
 *   docker exec chatpedidos_app php artisan erp:procesar-cola --limit=100
 */
class ErpProcesarCola extends Command
{
    protected $signature = 'erp:procesar-cola
                            {--limit=50 : Cuántos pendientes procesar por corrida}';

    protected $description = 'Reintenta sincronizar con el ERP los clientes/pedidos que fallaron por outage';

    public function handle(ErpRetryQueueService $queue): int
    {
        $limit = (int) $this->option('limit');

        $stats = $queue->procesarPendientes($limit);

        $this->info(sprintf(
            '🔄 ERP cola: procesados=%d ✅completados=%d ❌fallidos=%d 💥max=%d',
            $stats['procesados'],
            $stats['completados'],
            $stats['fallidos'],
            $stats['max_alcanzado']
        ));

        // Si todo está completado o vacío, return SUCCESS
        return self::SUCCESS;
    }
}
