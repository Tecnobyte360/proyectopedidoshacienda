<?php

namespace App\Jobs;

use App\Models\CampanaWhatsapp;
use App\Services\CampanaSenderService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 📨 Job que dispara el envío de una campaña en el momento programado.
 *
 * Flujo:
 *  - Campaña sin fecha programada → dispatch() inmediato (corre apenas haya worker libre)
 *  - Campaña con programada_para → dispatch()->delay($programada_para)
 *    El worker la mantiene en cola hasta la hora exacta y luego la procesa.
 *
 * Si por alguna razón el job falla, el cron campanas:procesar funciona como
 * red de seguridad y procesa cualquier campaña en estado 'corriendo' o
 * 'programada' que el job hubiera dejado pendiente.
 */
class ProcesarCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campanaId;

    /** Reintentos automáticos si falla */
    public int $tries = 3;

    /** Timeout 5 min (suficiente para lotes grandes con sleeps) */
    public int $timeout = 300;

    // ⚠️ NO redeclarar $connection/$queue como typed properties — el trait
    // Queueable ya las define como mixed. Usamos el constructor para setearlas.

    public function __construct(int $campanaId)
    {
        $this->campanaId = $campanaId;
        // Forzar uso de la cola database (no sync) para que delay() funcione
        $this->onConnection('database');
        $this->onQueue('campanas');
    }

    public function handle(CampanaSenderService $sender, TenantManager $tm): void
    {
        $c = CampanaWhatsapp::withoutGlobalScopes()->find($this->campanaId);
        if (!$c) {
            Log::warning("ProcesarCampanaJob: campaña #{$this->campanaId} no existe");
            return;
        }

        // Setear contexto de tenant (multitenancy)
        $tenant = \App\Models\Tenant::find($c->tenant_id);
        if (!$tenant) {
            Log::warning("ProcesarCampanaJob: tenant {$c->tenant_id} no existe");
            return;
        }
        $tm->set($tenant);

        // Si está programada y ya es hora → activar
        if ($c->estado === CampanaWhatsapp::ESTADO_PROGRAMADA) {
            // Garantizar audiencia
            if ($c->total_destinatarios === 0) {
                $sender->generarAudiencia($c);
                $c->refresh();
            }

            $c->update([
                'estado'      => CampanaWhatsapp::ESTADO_CORRIENDO,
                'iniciada_at' => $c->iniciada_at ?: now(),
            ]);
            Log::info("📨 Campaña #{$c->id} '{$c->nombre}' activada por job a las " . now());
        }

        // Solo procesar si está corriendo
        if ($c->estado !== CampanaWhatsapp::ESTADO_CORRIENDO) {
            Log::info("ProcesarCampanaJob: campaña #{$c->id} en estado '{$c->estado}', no se procesa");
            return;
        }

        // Procesar lote — esto ya tiene anti-baneo (sleep entre mensajes)
        $r = $sender->procesarLote($c);
        Log::info("ProcesarCampanaJob: campaña #{$c->id} → {$r['enviados']} enviados, {$r['fallidos']} fallidos, razón: {$r['razon']}");

        // Si todavía hay pendientes, encolar otro job para seguir procesando
        // (respeta el descanso entre lotes configurado)
        $c->refresh();
        if ($c->estado === CampanaWhatsapp::ESTADO_CORRIENDO && $c->total_pendientes > 0) {
            $delaySegundos = max(30, $c->descanso_lote_min * 60);
            $job = (new static($c->id))->delay(now()->addSeconds($delaySegundos));
            dispatch($job);
            Log::info("ProcesarCampanaJob: campaña #{$c->id} tiene {$c->total_pendientes} pendientes, próximo lote en {$delaySegundos}s");
        }

        $tm->set(null);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcesarCampanaJob FALLÓ para campaña #{$this->campanaId}: " . $exception->getMessage(), [
            'trace' => mb_substr($exception->getTraceAsString(), 0, 500),
        ]);
    }
}
