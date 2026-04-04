<?php

namespace App\Livewire\Pedidos;

use Livewire\Component;
use App\Models\Pedido;

class Index extends Component
{
    public $pedidos = [];

    protected $listeners = [
        'pedidoActualizado' => 'cargarPedidos',
    ];

    public function mount()
    {
        $this->cargarPedidos();
    }

    public function cargarPedidos()
    {
        $this->pedidos = Pedido::with(['detalles', 'sede'])
            ->latest()
            ->get();
    }

    public function marcarEnPreparacion($pedidoId)
    {
        $pedido = Pedido::findOrFail($pedidoId);

        $pedido->cambiarEstado(
            Pedido::ESTADO_EN_PREPARACION,
            'Tu pedido ya está en preparación.',
            'Pedido en preparación',
            auth()->user()?->name,
            auth()->id()
        );

        $this->cargarPedidos();
        $this->dispatch('pedidoActualizado');
    }

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos' => $this->pedidos,
        ])->layout('layouts.app');
    }
}