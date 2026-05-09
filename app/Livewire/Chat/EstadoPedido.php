<?php

namespace App\Livewire\Chat;

use App\Models\ConversacionPedidoEstado;
use App\Models\ConversacionWhatsapp;
use App\Services\EstadoPedidoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Panel /chat/estado/{id} — vista en tiempo real del estado estructurado
 * del pedido para la conversación. Útil para que el operador vea qué
 * datos tiene el bot recolectados sin tener que leer la conversación entera.
 */
class EstadoPedido extends Component
{
    public ConversacionWhatsapp $conversacion;
    public ?ConversacionPedidoEstado $estado = null;

    public function mount(ConversacionWhatsapp $conversacion): void
    {
        $this->conversacion = $conversacion;
        $this->cargar();
    }

    public function cargar(): void
    {
        $this->estado = app(EstadoPedidoService::class)->obtener($this->conversacion);
    }

    public function resetear(): void
    {
        app(EstadoPedidoService::class)->resetear($this->conversacion, 'reset_manual_admin');
        $this->cargar();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '🔄 Estado del pedido reseteado. La próxima respuesta del bot empezará limpia.',
        ]);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.chat.estado-pedido');
    }
}
