<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public $pedidos = [];

    protected $listeners = [
        'pedidoActualizado' => 'cargarPedidos',
    ];

    public function mount(): void
    {
        $this->cargarPedidos();
    }

    public function cargarPedidos(): void
    {
        $this->pedidos = Pedido::with([
                'detalles',
                'sede',
            ])
            ->latest()
            ->get();
    }

    public function marcarEnPreparacion(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);

        if ($pedido->estado !== Pedido::ESTADO_NUEVO) {
            return;
        }

        $usuario = Auth::user();

        $pedido->cambiarEstado(
            Pedido::ESTADO_EN_PREPARACION,
            'Tu pedido ya está en preparación.',
            'Pedido en preparación',
            $usuario?->name,
            $usuario?->id
        );

        $this->cargarPedidos();
        $this->dispatch('pedidoActualizado');
    }

    public function marcarEnCamino(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);

        if ($pedido->estado !== Pedido::ESTADO_EN_PREPARACION) {
            return;
        }

        $usuario = Auth::user();

        $pedido->cambiarEstado(
            Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
            'Tu pedido ya va en camino.',
            'Pedido en camino',
            $usuario?->name,
            $usuario?->id
        );

        $this->cargarPedidos();
        $this->dispatch('pedidoActualizado');
    }

    public function marcarEntregado(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);

        if ($pedido->estado !== Pedido::ESTADO_REPARTIDOR_EN_CAMINO) {
            return;
        }

        $usuario = Auth::user();

        $pedido->cambiarEstado(
            Pedido::ESTADO_ENTREGADO,
            'Tu pedido fue entregado correctamente.',
            'Pedido entregado',
            $usuario?->name,
            $usuario?->id
        );

        $this->cargarPedidos();
        $this->dispatch('pedidoActualizado');
    }

    public function cancelarPedido(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);

        if (in_array($pedido->estado, [
            Pedido::ESTADO_ENTREGADO,
            Pedido::ESTADO_CANCELADO,
        ], true)) {
            return;
        }

        $usuario = Auth::user();

        $pedido->cambiarEstado(
            Pedido::ESTADO_CANCELADO,
            'Tu pedido fue cancelado.',
            'Pedido cancelado',
            $usuario?->name,
            $usuario?->id
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