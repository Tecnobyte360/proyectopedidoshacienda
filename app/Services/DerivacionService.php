<?php

namespace App\Services;

use App\Models\ConversacionWhatsapp;
use App\Models\Departamento;
use App\Models\UsuarioInternoWhatsapp;
use Illuminate\Support\Facades\Log;

/**
 * Deriva conversaciones a departamentos cuando se detecta una intención
 * específica (palabras clave). Notifica por WhatsApp a los usuarios internos
 * del departamento para que tomen la conversación.
 */
class DerivacionService
{
    /**
     * Intenta derivar un mensaje entrante a un departamento.
     * Devuelve el saludo automático si se derivó (para enviarlo al cliente), o null si no aplica.
     */
    public function derivarSiAplica(
        ConversacionWhatsapp $conv,
        string $mensaje,
        string $clienteNombre,
        string $clienteTelefono
    ): ?string {
        // Si la conversación YA está derivada a un departamento, no volvemos a derivar.
        if ($conv->departamento_id) {
            return null;
        }

        $depto = Departamento::detectarPorMensaje($mensaje);
        if (!$depto) return null;

        // Marcar conversación como derivada
        $conv->update([
            'departamento_id'     => $depto->id,
            'derivada_at'         => now(),
            'atendida_por_humano' => true,   // para que el bot ya no conteste
        ]);

        Log::info('🎯 Conversación derivada a departamento', [
            'conv_id' => $conv->id, 'dpto' => $depto->nombre, 'mensaje' => mb_substr($mensaje, 0, 80),
        ]);

        // Notificar por WhatsApp a los usuarios internos del departamento
        if ($depto->notificar_internos) {
            $this->notificarDepartamento($depto, $clienteNombre, $clienteTelefono, $mensaje, $conv->connection_id);
        }

        // Saludo automático al cliente
        return $depto->saludo_automatico
            ?: "¡Hola {$clienteNombre}! 🙌 Un asesor del área de *{$depto->nombre}* te atenderá en breve.";
    }

    /**
     * Manda un mensaje a cada teléfono interno del departamento con el
     * contexto del cliente y su mensaje.
     */
    private function notificarDepartamento(
        Departamento $depto,
        string $clienteNombre,
        string $clienteTelefono,
        string $mensaje,
        $connectionId
    ): void {
        $usuarios = UsuarioInternoWhatsapp::withoutGlobalScopes()
            ->where('tenant_id', $depto->tenant_id)
            ->where('departamento_id', $depto->id)
            ->where('activo', true)
            ->get();

        if ($usuarios->isEmpty()) {
            Log::warning("Departamento {$depto->nombre} sin usuarios internos activos — nadie fue notificado");
            return;
        }

        $cortado = mb_strimwidth($mensaje, 0, 200, '…');
        $texto = "🔔 *Nueva consulta para {$depto->nombre}*\n\n"
               . "👤 *Cliente:* {$clienteNombre}\n"
               . "📞 *Teléfono:* {$clienteTelefono}\n\n"
               . "💬 *Dice:*\n{$cortado}\n\n"
               . "_Responde directo al cliente desde la plataforma o por WhatsApp._";

        $sender = app(WhatsappSenderService::class);
        foreach ($usuarios as $u) {
            try {
                $sender->enviarTexto($u->telefono_normalizado, $texto, $connectionId);
            } catch (\Throwable $e) {
                Log::warning("Fallo notificar a {$u->telefono_normalizado}: " . $e->getMessage());
            }
        }
    }
}
