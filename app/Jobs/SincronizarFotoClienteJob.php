<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\FotoPerfilWhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 📸 SINCRONIZAR FOTO CLIENTE
 *
 * Corre en background para no bloquear el webhook. Llama al servicio
 * que consulta EstradaHub y descarga la foto.
 *
 * Idempotente: si la foto está reciente (<7 días) no hace nada.
 *
 * Throttle: por cache, mismo cliente solo se intenta 1 vez cada 5 min
 * para evitar martillar la API si fallan los reintentos.
 */
class SincronizarFotoClienteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $clienteId, public bool $forzar = false)
    {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function handle(FotoPerfilWhatsappService $servicio): void
    {
        // Throttle por cliente — evitar reintentos seguidos si la API tarda
        $lockKey = "foto_cliente_lock_{$this->clienteId}";
        if (\Cache::has($lockKey) && !$this->forzar) {
            return;
        }
        \Cache::put($lockKey, true, now()->addMinutes(5));

        $cliente = Cliente::withoutGlobalScopes()->find($this->clienteId);
        if (!$cliente) {
            Log::warning('📸 SincronizarFotoClienteJob: cliente no encontrado', ['id' => $this->clienteId]);
            return;
        }

        try {
            $servicio->sincronizar($cliente, $this->forzar);
        } catch (\Throwable $e) {
            Log::warning('📸 SincronizarFotoClienteJob falló: ' . $e->getMessage(), [
                'cliente_id' => $this->clienteId,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('📸 SincronizarFotoClienteJob FAILED', [
            'cliente_id' => $this->clienteId,
            'error'      => $e->getMessage(),
        ]);
    }
}
