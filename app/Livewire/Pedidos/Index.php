<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

      dd('ENTRÓ AL MÉTODO', $pedidoId);
        Log::info('CLICK iniciar preparación', [
            'pedido_id' => $pedidoId,
        ]);

        $pedido = Pedido::findOrFail($pedidoId);

        $estadoActual = trim((string) $pedido->estado);

        Log::info('Pedido cargado para iniciar preparación', [
            'id' => $pedido->id,
            'estado_actual' => $estadoActual,
            'estado_nuevo_constante' => Pedido::ESTADO_NUEVO,
        ]);

        if (in_array($estadoActual, [
            Pedido::ESTADO_CANCELADO,
            Pedido::ESTADO_ENTREGADO,
            Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
            Pedido::ESTADO_RECOGIDO,
        ], true)) {
            Log::warning('No se puede pasar a preparación por estado no permitido', [
                'pedido_id' => $pedido->id,
                'estado_actual' => $estadoActual,
            ]);

            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "El pedido #{$pedido->id} no se puede pasar a preparación porque está en estado: {$estadoActual}.",
            ]);

            return;
        }

        if ($estadoActual === Pedido::ESTADO_EN_PREPARACION) {
            Log::info('El pedido ya estaba en preparación', [
                'pedido_id' => $pedido->id,
            ]);

            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "El pedido #{$pedido->id} ya está en preparación.",
            ]);

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

        Log::info('Estado cambiado correctamente a preparación', [
            'pedido_id' => $pedido->id,
            'nuevo_estado' => $pedido->fresh()->estado,
        ]);

        $this->cargarPedidos();
        $this->dispatch('pedidoActualizado');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Pedido #{$pedido->id} enviado a preparación correctamente.",
        ]);
    }

    public function marcarEnCamino(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if ($estadoActual !== Pedido::ESTADO_EN_PREPARACION) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "El pedido #{$pedido->id} debe estar en preparación antes de pasarlo a en camino.",
            ]);
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

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Pedido #{$pedido->id} marcado como en camino.",
        ]);
    }

    public function marcarEntregado(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if ($estadoActual !== Pedido::ESTADO_REPARTIDOR_EN_CAMINO) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "El pedido #{$pedido->id} debe estar en camino antes de marcarlo como entregado.",
            ]);
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

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Pedido #{$pedido->id} marcado como entregado.",
        ]);
    }

    public function cancelarPedido(int $pedidoId): void
    {
        $pedido = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if (in_array($estadoActual, [
            Pedido::ESTADO_ENTREGADO,
            Pedido::ESTADO_CANCELADO,
        ], true)) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "El pedido #{$pedido->id} no se puede cancelar porque está en estado: {$estadoActual}.",
            ]);
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

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Pedido #{$pedido->id} cancelado correctamente.",
        ]);
    }

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos' => $this->pedidos,
        ])->layout('layouts.app');
    }
}