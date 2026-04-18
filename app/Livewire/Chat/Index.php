<?php

namespace App\Livewire\Chat;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\ConversacionService;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public ?int $conversacionActivaId = null;

    public string $nuevoMensaje = '';
    public string $busqueda     = '';
    public string $filtroEstado = 'todas';   // todas | activa | humano | bot

    public function seleccionar(int $id): void
    {
        $this->conversacionActivaId = $id;
        $this->nuevoMensaje = '';
        $this->dispatch('chat-cambiado', conversacionId: $id);
    }

    #[On('refrescar-chat')]
    public function refrescar(): void
    {
        // Solo dispara render
    }

    public function tomarControl(): void
    {
        if (!$this->conversacionActivaId) return;

        ConversacionWhatsapp::find($this->conversacionActivaId)
            ?->update(['atendida_por_humano' => true]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✋ Tomaste el control de la conversación. El bot ya no responde.',
        ]);
    }

    public function devolverAlBot(): void
    {
        if (!$this->conversacionActivaId) return;

        ConversacionWhatsapp::find($this->conversacionActivaId)
            ?->update(['atendida_por_humano' => false]);

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => '🤖 El bot retoma la conversación.',
        ]);
    }

    public function enviar(): void
    {
        $texto = trim($this->nuevoMensaje);
        if ($texto === '' || !$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) return;

        // Si no estaba en modo humano, lo activamos automáticamente
        if (!$conv->atendida_por_humano) {
            $conv->update(['atendida_por_humano' => true]);
        }

        // Enviar a WhatsApp via el método del controller
        try {
            $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
            // Llamar al método público enviarMensajeManual a través de un Request fake
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'conversacion_id' => $conv->id,
                'mensaje'         => $texto,
            ]);

            $resp = $controller->enviarMensajeManual($request);

            if ($resp->getStatusCode() !== 200) {
                $this->dispatch('notify', [
                    'type'    => 'error',
                    'message' => 'No se pudo enviar el mensaje a WhatsApp.',
                ]);
                return;
            }

            $this->nuevoMensaje = '';

            $this->dispatch('mensaje-enviado');
        } catch (\Throwable $e) {
            \Log::error('Error enviando mensaje manual: ' . $e->getMessage());
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $conversaciones = ConversacionWhatsapp::query()
            ->with('cliente')
            ->where('estado', '!=', 'archivada')
            ->when($this->busqueda, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('telefono_normalizado', 'like', "%{$this->busqueda}%")
                       ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->busqueda}%"));
                });
            })
            ->when($this->filtroEstado === 'activa', fn ($q) => $q->where('estado', 'activa'))
            ->when($this->filtroEstado === 'humano', fn ($q) => $q->where('atendida_por_humano', true))
            ->when($this->filtroEstado === 'bot',    fn ($q) => $q->where('atendida_por_humano', false))
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(60)
            ->get();

        $conversacionActiva = $this->conversacionActivaId
            ? ConversacionWhatsapp::with(['cliente', 'mensajes', 'pedido'])->find($this->conversacionActivaId)
            : null;

        return view('livewire.chat.index', compact('conversaciones', 'conversacionActiva'))
            ->layout('layouts.app');
    }
}
