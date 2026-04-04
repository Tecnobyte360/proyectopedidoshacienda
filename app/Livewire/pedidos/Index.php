<?php

namespace App\Livewire\Pedidos;

use Livewire\Component;
use App\Models\Pedido;

class Index extends Component
{
    public $pedidos = [];

    public $mostrarModalEstado = false;
    public $pedidoSeleccionadoId = null;
    public $nuevoEstado = '';
    public $tituloEstado = '';
    public $descripcionEstado = '';

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

    public function abrirModalEstado($pedidoId)
    {
        $pedido = Pedido::findOrFail($pedidoId);

        $this->pedidoSeleccionadoId = $pedido->id;
        $this->nuevoEstado = $pedido->estado ?? Pedido::ESTADO_NUEVO;
        $this->tituloEstado = '';
        $this->descripcionEstado = '';
        $this->mostrarModalEstado = true;
    }

    public function cerrarModalEstado()
    {
        $this->mostrarModalEstado = false;
        $this->pedidoSeleccionadoId = null;
        $this->nuevoEstado = '';
        $this->tituloEstado = '';
        $this->descripcionEstado = '';
    }

    public function actualizarEstadoPedido()
    {
        $this->validate([
            'pedidoSeleccionadoId' => 'required|exists:pedidos,id',
            'nuevoEstado' => 'required|string|in:' . implode(',', array_keys(Pedido::estadosDisponibles())),
            'tituloEstado' => 'nullable|string|max:150',
            'descripcionEstado' => 'nullable|string|max:1000',
        ]);

        $pedido = Pedido::findOrFail($this->pedidoSeleccionadoId);

        $pedido->cambiarEstado(
            $this->nuevoEstado,
            $this->descripcionEstado ?: null,
            $this->tituloEstado ?: null,
            auth()->user()?->name,
            auth()->id()
        );

        $this->cargarPedidos();
        $this->cerrarModalEstado();

        $this->dispatch('pedidoActualizado');
    }

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos' => collect($this->pedidos),
            'estadosDisponibles' => Pedido::estadosDisponibles(),
            'mostrarModalEstado' => $this->mostrarModalEstado,
            'pedidoSeleccionadoId' => $this->pedidoSeleccionadoId,
        ])->layout('layouts.app');
    }
}