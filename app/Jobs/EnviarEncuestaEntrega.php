<?php

namespace App\Jobs;

use App\Models\EncuestaPedido;
use App\Services\WhatsappSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarEncuestaEntrega implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $encuestaId,
        public string $telefono,
        public string $mensaje,
    ) {}

    public function handle(WhatsappSenderService $wa): void
    {
        $encuesta = EncuestaPedido::find($this->encuestaId);
        if (!$encuesta) return;
        if ($encuesta->enviada_at !== null) return; // ya se envió

        // Resolver tenant del pedido para que el sender use sus credenciales
        try {
            $tenant = \App\Services\TenantManager::class
                ? app(\App\Services\TenantManager::class)->withoutTenant(
                    fn () => \App\Models\Tenant::find($encuesta->tenant_id)
                  )
                : null;
        } catch (\Throwable $e) {
            $tenant = null;
        }

        if ($tenant) {
            app(\App\Services\TenantManager::class)->set($tenant);
        }

        $connectionId = null;
        $idsValidos = [];

        // 1. Obtener TODAS las conexiones válidas vivas del tenant (auto-recupera orphans)
        try {
            $idsValidos = app(\App\Services\WhatsappResolverService::class)->connectionIdsValidos($tenant);
        } catch (\Throwable $e) {
            Log::warning('No se pudo obtener connectionIdsValidos: ' . $e->getMessage());
        }

        // 2. Preferir el connection_id del pedido o de la sede SI ESTÁ EN LA LISTA VÁLIDA
        try {
            $pedido = $encuesta->pedido;
            if ($pedido) {
                if ($pedido->connection_id && in_array((int) $pedido->connection_id, $idsValidos, true)) {
                    $connectionId = (int) $pedido->connection_id;
                } elseif ($pedido->sede_id) {
                    $sede = \App\Models\Sede::find($pedido->sede_id);
                    if ($sede && $sede->whatsapp_connection_id && in_array((int) $sede->whatsapp_connection_id, $idsValidos, true)) {
                        $connectionId = (int) $sede->whatsapp_connection_id;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignorar */ }

        // 3. Si el del pedido/sede ya no es válido, usar el primer válido disponible
        if (!$connectionId && !empty($idsValidos)) {
            $connectionId = $idsValidos[0];
            Log::info('Encuesta: connection_id del pedido es orphan, usando alternativo', [
                'pedido_connection_id' => $pedido->connection_id ?? null,
                'connection_id_usado'  => $connectionId,
            ]);

            // Auto-curar el pedido para futuras notificaciones
            try {
                if ($pedido && $pedido->connection_id !== $connectionId) {
                    $pedido->update(['connection_id' => $connectionId, 'whatsapp_id' => $connectionId]);
                }
            } catch (\Throwable $e) {}
        }

        if (!$connectionId) {
            Log::error('Encuesta: NO HAY connection_ids válidos en el tenant', [
                'tenant_id' => $tenant?->id,
                'encuesta_id' => $encuesta->id,
            ]);
            throw new \RuntimeException('No hay conexión WhatsApp válida en este tenant — escanea el QR.');
        }

        $ok = $wa->enviarTexto(
            $this->telefono,
            $this->mensaje,
            $connectionId,
            true,
            ['origen' => 'encuesta_entrega', 'encuesta_id' => $encuesta->id]
        );

        if ($ok) {
            $encuesta->update(['enviada_at' => now()]);
            Log::info('📋 Encuesta enviada al cliente', [
                'encuesta_id' => $encuesta->id,
                'pedido_id'   => $encuesta->pedido_id,
                'tel'         => $this->telefono,
            ]);
        } else {
            Log::warning('Encuesta no enviada', ['encuesta_id' => $encuesta->id]);
            throw new \RuntimeException('No se pudo enviar la encuesta');
        }
    }
}
