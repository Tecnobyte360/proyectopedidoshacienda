<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public $pedidos;
    public string $estado = 'todos';
    public string $zona   = 'todas';

    // Modal token entrega
    public bool   $modalTokenAbierto  = false;
    public int    $pedidoIdEntregando = 0;
    public string $tokenIngresado     = '';
    public string $tokenError         = '';

    protected $listeners = [
        'pedidoActualizado' => 'cargarPedidos',
    ];

    protected $queryString = [
        'estado' => ['except' => 'todos'],
        'zona'   => ['except' => 'todas'],
    ];

    public function mount(): void
    {
        $this->cargarPedidos();
    }

    public function cargarPedidos(): void
    {
        $this->pedidos = Pedido::with(['detalles', 'sede'])->latest()->get();
    }

    public function cambiarTab(string $estado): void
    {
        $this->estado = $estado;
    }

    public function updatedZona(): void {}

    public function getPedidosFiltradosProperty()
    {
        $pedidos = $this->pedidos ?? collect();

        if ($this->estado !== 'todos') {
            $pedidos = $pedidos->where('estado', $this->estado);
        }

        if ($this->zona !== 'todas') {
            $pedidos = $pedidos->where('zona', $this->zona);
        }

        return $pedidos;
    }

    public function marcarEnPreparacion(int $pedidoId): void
    {
        $pedido       = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if (in_array($estadoActual, [
            Pedido::ESTADO_CANCELADO,
            Pedido::ESTADO_ENTREGADO,
            Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
            Pedido::ESTADO_RECOGIDO,
        ], true)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => "El pedido #{$pedido->id} no se puede pasar a preparación."]);
            return;
        }

        if ($estadoActual === Pedido::ESTADO_EN_PREPARACION) {
            $this->dispatch('notify', ['type' => 'info', 'message' => "El pedido #{$pedido->id} ya está en preparación."]);
            return;
        }

        $usuario = Auth::user();
        $pedido->cambiarEstado(Pedido::ESTADO_EN_PREPARACION, 'Tu pedido ya está en preparación.', 'Pedido en preparación', $usuario?->name, $usuario?->id);

        $this->cargarPedidos();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Pedido #{$pedido->id} enviado a preparación."]);
    }

    public function marcarEnCamino(int $pedidoId): void
    {
        $pedido       = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if ($estadoActual !== Pedido::ESTADO_EN_PREPARACION) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => "El pedido #{$pedido->id} debe estar en preparación antes de despacharlo."]);
            return;
        }

        $usuario = Auth::user();

        // 1. Generar y guardar token
        $token = $pedido->generarTokenEntrega();

        // 2. Cambiar estado (NO envía WhatsApp porque lo interceptamos en el modelo)
        $pedido->cambiarEstado(Pedido::ESTADO_REPARTIDOR_EN_CAMINO, 'Tu pedido ya va en camino.', 'Pedido en camino', $usuario?->name, $usuario?->id);

        // 3. Enviar WhatsApp con token incluido
        $pedido->notificarTokenEntrega($token);

        $this->cargarPedidos();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "Pedido #{$pedido->id} despachado. Token {$token} enviado al cliente por WhatsApp.",
        ]);
    }

    // Abre el modal y limpia estado anterior
    public function abrirModalEntrega(int $pedidoId): void
    {
        $this->pedidoIdEntregando = $pedidoId;
        $this->tokenIngresado     = '';
        $this->tokenError         = '';
        $this->modalTokenAbierto  = true;
    }

    public function cerrarModalEntrega(): void
    {
        $this->modalTokenAbierto  = false;
        $this->tokenIngresado     = '';
        $this->tokenError         = '';
        $this->pedidoIdEntregando = 0;
    }

    public function confirmarEntregaConToken(): void
    {
        $this->tokenError = '';

        if (strlen(trim($this->tokenIngresado)) !== 4) {
            $this->tokenError = 'El token debe tener exactamente 4 dígitos.';
            return;
        }

        $pedido = Pedido::findOrFail($this->pedidoIdEntregando);

        if (trim($this->tokenIngresado) !== trim((string) $pedido->token_entrega)) {
            $this->tokenError = 'Token incorrecto. Verifica el código con el cliente.';
            return;
        }

        if (trim((string) $pedido->estado) !== Pedido::ESTADO_REPARTIDOR_EN_CAMINO) {
            $this->tokenError = 'Este pedido ya no está en camino.';
            return;
        }

        $usuario = Auth::user();
        $pedido->cambiarEstado(Pedido::ESTADO_ENTREGADO, 'Tu pedido fue entregado correctamente.', 'Pedido entregado', $usuario?->name, $usuario?->id);

        $this->cerrarModalEntrega();
        $this->cargarPedidos();

        $this->dispatch('notify', ['type' => 'success', 'message' => "Pedido #{$pedido->id} marcado como entregado correctamente."]);
    }

    public function cancelarPedido(int $pedidoId): void
    {
        $pedido       = Pedido::findOrFail($pedidoId);
        $estadoActual = trim((string) $pedido->estado);

        if (in_array($estadoActual, [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO], true)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => "El pedido #{$pedido->id} no se puede cancelar."]);
            return;
        }

        $usuario = Auth::user();
        $pedido->cambiarEstado(Pedido::ESTADO_CANCELADO, 'Tu pedido fue cancelado.', 'Pedido cancelado', $usuario?->name, $usuario?->id);

        $this->cargarPedidos();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Pedido #{$pedido->id} cancelado."]);
    }

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos'          => $this->pedidos,
            'pedidosFiltrados' => $this->pedidosFiltrados,
        ])->layout('layouts.app');
    }
}