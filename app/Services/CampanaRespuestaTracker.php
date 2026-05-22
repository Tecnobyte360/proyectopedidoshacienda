<?php

namespace App\Services;

use App\Models\CampanaDestinatario;
use Illuminate\Support\Facades\Log;

/**
 * Detecta y marca cuando un cliente responde a una campaña.
 *
 * Como TecnoByteApp no envía webhooks de ACK (delivered/read), la mejor
 * métrica de "el cliente vio el mensaje" es: ¿respondió algo después de
 * que le mandamos la campaña? Si sí, lo vio y le interesó.
 *
 * Se llama desde el WhatsappWebhookController cuando llega un mensaje
 * entrante (fromMe=false) — pero ese flujo ya es complejo, así que
 * exponemos una API simple aquí.
 */
class CampanaRespuestaTracker
{
    /**
     * Ventana de tiempo: si la respuesta llega dentro de N días desde el
     * envío de la campaña, contamos como "respuesta a la campaña".
     */
    private const VENTANA_DIAS_RESPUESTA = 7;

    /**
     * Marca un teléfono como "respondió" en todas las campañas recientes
     * donde ese teléfono fue destinatario y aún no había respondido.
     *
     * @return int Cantidad de campañas marcadas (suele ser 0 o 1)
     */
    public function marcarRespuestaDe(string $telefono, ?int $tenantId = null): int
    {
        $telefonoNorm = preg_replace('/\D+/', '', $telefono);
        if (mb_strlen($telefonoNorm) < 7) return 0;

        // Buscar destinatarios que cumplan:
        // - mismo teléfono (normalizado)
        // - estado enviado (no fallidos)
        // - enviados en los últimos N días
        // - aún sin respondio_at
        $query = CampanaDestinatario::query()
            ->where('telefono', $telefonoNorm)
            ->where('estado', CampanaDestinatario::ESTADO_ENVIADO)
            ->whereNull('respondio_at')
            ->where('enviado_at', '>=', now()->subDays(self::VENTANA_DIAS_RESPUESTA));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $destinatarios = $query->get();

        if ($destinatarios->isEmpty()) return 0;

        $now = now();
        $marcados = 0;
        foreach ($destinatarios as $d) {
            $d->update([
                'respondio_at'     => $now,
                'respuestas_count' => 1,
            ]);
            $marcados++;
            Log::info("📩 Campaña #{$d->campana_id}: {$telefonoNorm} respondió → marcado como visto");
        }

        return $marcados;
    }

    /**
     * Incrementa el contador de respuestas si el cliente ya había respondido.
     * Útil para ver "qué tan engaged" están los que sí responden.
     */
    public function contabilizarRespuestaAdicional(string $telefono, ?int $tenantId = null): void
    {
        $telefonoNorm = preg_replace('/\D+/', '', $telefono);
        if (mb_strlen($telefonoNorm) < 7) return;

        $query = CampanaDestinatario::query()
            ->where('telefono', $telefonoNorm)
            ->whereNotNull('respondio_at')
            ->where('enviado_at', '>=', now()->subDays(self::VENTANA_DIAS_RESPUESTA));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $query->increment('respuestas_count');
    }

    /**
     * Punto de entrada principal: llamar desde el webhook cuando llega un
     * mensaje entrante. Decide automáticamente si es primera respuesta o
     * respuesta adicional.
     */
    public function procesarMensajeEntrante(string $telefono, ?int $tenantId = null): void
    {
        $telefonoNorm = preg_replace('/\D+/', '', $telefono);
        if (mb_strlen($telefonoNorm) < 7) return;

        $existeRespuesta = CampanaDestinatario::where('telefono', $telefonoNorm)
            ->whereNotNull('respondio_at')
            ->where('enviado_at', '>=', now()->subDays(self::VENTANA_DIAS_RESPUESTA))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->exists();

        if ($existeRespuesta) {
            $this->contabilizarRespuestaAdicional($telefonoNorm, $tenantId);
        } else {
            $this->marcarRespuestaDe($telefonoNorm, $tenantId);
        }
    }
}
