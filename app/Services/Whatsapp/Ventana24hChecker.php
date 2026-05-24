<?php

namespace App\Services\Whatsapp;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;

/**
 * Verifica si una conversación está dentro de la ventana de 24h de Meta.
 *
 * Regla WhatsApp: una empresa puede mandar texto libre a un cliente SOLO si
 * el cliente respondió en las últimas 24h. Fuera de eso, debe usar plantilla.
 * Esta clase resuelve esa pregunta para una conversación o número.
 */
class Ventana24hChecker
{
    /**
     * @return bool true si la conversación está dentro de 24h (texto libre OK)
     */
    public function abierta(ConversacionWhatsapp $conv): bool
    {
        $ultimoEntrante = MensajeWhatsapp::withoutGlobalScopes()
            ->where('conversacion_id', $conv->id)
            ->where('rol', MensajeWhatsapp::ROL_USER)
            ->orderByDesc('id')
            ->first();

        if (!$ultimoEntrante) return false;

        return $ultimoEntrante->created_at?->gt(now()->subHours(24)) ?? false;
    }

    /**
     * Para usar cuando no tienes la conversación cargada — busca por teléfono.
     */
    public function abiertaPorTelefono(string $telefonoNormalizado): bool
    {
        $conv = ConversacionWhatsapp::withoutGlobalScopes()
            ->where('telefono_normalizado', $telefonoNormalizado)
            ->orderByDesc('id')
            ->first();

        return $conv ? $this->abierta($conv) : false;
    }

    /**
     * Minutos restantes antes de que se cierre la ventana (0 si ya está cerrada).
     */
    public function minutosRestantes(ConversacionWhatsapp $conv): int
    {
        $ultimoEntrante = MensajeWhatsapp::withoutGlobalScopes()
            ->where('conversacion_id', $conv->id)
            ->where('rol', MensajeWhatsapp::ROL_USER)
            ->orderByDesc('id')
            ->first();

        if (!$ultimoEntrante || !$ultimoEntrante->created_at) return 0;

        $cierre = $ultimoEntrante->created_at->copy()->addHours(24);
        if ($cierre->isPast()) return 0;

        return (int) abs(now()->diffInMinutes($cierre));
    }
}
