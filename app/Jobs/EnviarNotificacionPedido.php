<?php

namespace App\Jobs;

use App\Services\WhatsappSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía un mensaje de WhatsApp al cliente con delay opcional.
 * Usado para notificaciones de cambio de estado y pagos donde el tenant
 * configuró un retraso en /configuracion/bot → Notificaciones.
 */
class EnviarNotificacionPedido implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $tenantId,
        public string $telefono,
        public string $mensaje,
        public ?int $connectionId = null,
        public string $tipo = 'generica',
        public ?int $pedidoId = null,
    ) {}

    public function handle(WhatsappSenderService $wa): void
    {
        // Activar contexto del tenant
        try {
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($this->tenantId);
            if ($tenant) {
                app(\App\Services\TenantManager::class)->set($tenant);
            }
        } catch (\Throwable $e) { /* ignorar */ }

        $ok = $wa->enviarTexto(
            $this->telefono,
            $this->mensaje,
            $this->connectionId,
            true,
            ['tipo_notificacion' => $this->tipo, 'pedido_id' => $this->pedidoId]
        );

        if (!$ok) {
            Log::warning('Notificación pedido no enviada', [
                'tipo' => $this->tipo,
                'pedido_id' => $this->pedidoId,
                'tel' => $this->telefono,
            ]);
            throw new \RuntimeException('No se pudo enviar la notificación');
        }

        Log::info('📤 Notificación pedido enviada', [
            'tipo' => $this->tipo,
            'pedido_id' => $this->pedidoId,
        ]);
    }
}
