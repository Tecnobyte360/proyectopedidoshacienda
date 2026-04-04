<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Livewire\Component;

class SeguimientoPedido extends Component
{
    public Pedido $pedido;

    public function mount(string $codigo)
    {
        $this->pedido = Pedido::with(['detalles', 'historialEstados'])
            ->where('codigo_seguimiento', $codigo)
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.pedidos.seguimiento-pedido', [
            'historial' => $this->pedido->historialEstados()->orderBy('fecha_evento')->get(),
        ])->layout('layouts.app');
    }
}
