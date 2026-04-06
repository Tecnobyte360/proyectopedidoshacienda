<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Livewire\Component;

class SeguimientoPedido extends Component
{
    public Pedido $pedido;
    public string $codigo;

    public function mount(string $codigo)
    {
        $this->codigo = $codigo;
        $this->cargarPedido();
    }

    public function cargarPedido(): void
    {
        $this->pedido = Pedido::with(['detalles', 'historialEstados'])
            ->where('codigo_seguimiento', $this->codigo)
            ->firstOrFail();
    }

    public function getListeners(): array
    {
        return [
            "echo:pedido-seguimiento.{$this->codigo},pedido.actualizado" => 'pedidoActualizadoEnTiempoReal',
        ];
    }

    public function pedidoActualizadoEnTiempoReal($event = null): void
    {
        $this->cargarPedido();
    }

    public function render()
    {
        return view('livewire.pedidos.seguimiento-pedido', [
            'historial' => $this->pedido->historialEstados()->orderBy('fecha_evento')->get(),
        ])->layout('layouts.app');
    }
}
