<?php

namespace App\Livewire\Pedidos;

use Livewire\Component;
use App\Models\Pedido;

class Index extends Component
{
    public $pedidos = [];

    public function mount()
    {
        $this->cargarPedidos();
    }

    public function cargarPedidos()
    {
        $this->pedidos = Pedido::latest()->get();
    }

    protected $listeners = [
        'pedidoActualizado' => 'cargarPedidos',
    ];

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos' => $this->pedidos,
        ])->layout('layouts.app');
    }
}