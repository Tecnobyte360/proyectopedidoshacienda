<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\AsignacionDomiciliarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🛵 Asigna domiciliarios a pedidos a domicilio que quedaron sin asignar.
 *
 * Por qué existe: el hook `created` de Pedido llama a AsignacionDomiciliarioService
 * pero a veces falla silenciosamente (transacción rollback, race condition,
 * config cambió justo antes). Este comando es la red de seguridad.
 *
 * Lógica:
 *  - Busca pedidos tipo_entrega=domicilio sin domiciliario_id
 *  - En estado nuevo/en_preparacion (no entregados/cancelados)
 *  - Solo de las últimas 24h (para no asignar pedidos viejos)
 *  - Aplica la auto-asignación si está activa en config
 *
 * Schedule: cada 2 minutos vía routes/console.php
 */
class AsignarPedidosHuerfanos extends Command
{
    protected $signature = 'pedidos:asignar-huerfanos
                            {--max=20 : Máximo a procesar por ciclo}
                            {--horas=24 : Solo pedidos de las últimas N horas}';

    protected $description = 'Asigna domiciliarios a pedidos a domicilio que quedaron sin asignar';

    public function handle(AsignacionDomiciliarioService $svc): int
    {
        $max   = (int) $this->option('max');
        $horas = (int) $this->option('horas');

        $huerfanos = Pedido::where('tipo_entrega', 'domicilio')
            ->whereNull('domiciliario_id')
            ->whereIn('estado', ['nuevo', 'en_preparacion'])
            ->where('created_at', '>=', now()->subHours($horas))
            ->orderBy('id')
            ->limit($max)
            ->get();

        if ($huerfanos->isEmpty()) {
            $this->info('No hay pedidos huérfanos.');
            return self::SUCCESS;
        }

        $this->info("🛵 Asignando {$huerfanos->count()} pedido(s) huérfano(s)...");

        $asignados = 0;
        $omitidos  = 0;

        foreach ($huerfanos as $pedido) {
            try {
                $dom = $svc->asignar($pedido);
                if ($dom) {
                    $this->info("  ✅ Pedido #{$pedido->id} → {$dom->nombre}");
                    $asignados++;
                } else {
                    $this->warn("  ⚠️  Pedido #{$pedido->id}: sin domiciliarios disponibles o config off");
                    $omitidos++;
                }
            } catch (\Throwable $e) {
                $this->error("  ❌ Pedido #{$pedido->id}: " . $e->getMessage());
                Log::warning('AsignarPedidosHuerfanos error', [
                    'pedido_id' => $pedido->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Resumen: ✅ {$asignados} asignados | ⚠️  {$omitidos} omitidos");
        return self::SUCCESS;
    }
}
