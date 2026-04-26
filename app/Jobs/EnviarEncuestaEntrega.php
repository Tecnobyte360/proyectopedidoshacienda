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

        // Preferir el connection_id de la sede del pedido (cada sede tiene su WhatsApp).
        try {
            $pedido = $encuesta->pedido;
            if ($pedido) {
                if ($pedido->connection_id) {
                    $connectionId = (int) $pedido->connection_id;
                } elseif ($pedido->sede_id) {
                    $sede = \App\Models\Sede::find($pedido->sede_id);
                    if ($sede && $sede->whatsapp_connection_id) {
                        $connectionId = (int) $sede->whatsapp_connection_id;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignorar */ }

        // Fallback al primer connection válido del tenant
        if (!$connectionId) {
            try {
                $ids = app(\App\Services\WhatsappResolverService::class)->connectionIdsValidos($tenant);
                $connectionId = $ids[0] ?? null;
            } catch (\Throwable $e) { /* ignorar */ }
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
