<?php

namespace App\Livewire\Pedidos;

use App\Models\Domiciliario;
use App\Models\Pedido;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public $pedidos;
    public $domiciliarios = [];

    public string $estado = 'todos';
    public string $zona   = 'todas';

    // Modal token entrega
    public bool   $modalTokenAbierto  = false;
    public int    $pedidoIdEntregando = 0;
    public string $tokenIngresado     = '';
    public string $tokenError         = '';

    // Modal despacho con domiciliario
    public bool $modalDespachoAbierto = false;
    public int $pedidoIdDespacho = 0;
    public ?int $domiciliarioSeleccionado = null;

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
        try {
            $this->pedidos = Pedido::with(['detalles', 'sede', 'domiciliario'])->latest()->get();
        } catch (\Throwable $e) {
            report($e);

            $this->pedidos = collect();

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudieron cargar los pedidos.',
            ]);
        }
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
        try {
            $pedido       = Pedido::findOrFail($pedidoId);
            $estadoActual = trim((string) $pedido->estado);

            if (in_array($estadoActual, [
                Pedido::ESTADO_CANCELADO,
                Pedido::ESTADO_ENTREGADO,
                Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                Pedido::ESTADO_RECOGIDO,
            ], true)) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} no se puede pasar a preparación.",
                ]);
                return;
            }

            if ($estadoActual === Pedido::ESTADO_EN_PREPARACION) {
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

            $this->cargarPedidos();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} enviado a preparación.",
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ocurrió un error al pasar el pedido a preparación.',
            ]);
        }
    }

    public function abrirModalDespacho(int $pedidoId): void
    {
        try {
            $pedido = Pedido::findOrFail($pedidoId);

            if (trim((string) $pedido->estado) !== Pedido::ESTADO_EN_PREPARACION) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} debe estar en preparación para despacharlo.",
                ]);
                return;
            }

            $this->pedidoIdDespacho = $pedidoId;
            $this->domiciliarioSeleccionado = $pedido->domiciliario_id ?: null;
            $this->modalDespachoAbierto = true;

            $this->domiciliarios = Domiciliario::where('activo', true)
                ->where(function ($q) use ($pedido) {
                    $q->where('estado', 'disponible');

                    if ($pedido->domiciliario_id) {
                        $q->orWhere('id', $pedido->domiciliario_id);
                    }
                })
                ->orderBy('nombre')
                ->get();
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudo abrir la asignación del domiciliario.',
            ]);
        }
    }

    public function cerrarModalDespacho(): void
    {
        $this->modalDespachoAbierto = false;
        $this->pedidoIdDespacho = 0;
        $this->domiciliarioSeleccionado = null;
        $this->domiciliarios = [];
        $this->resetValidation();
    }

    public function confirmarDespacho(): void
    {
        $this->validate([
            'domiciliarioSeleccionado' => 'required|exists:domiciliarios,id',
        ], [
            'domiciliarioSeleccionado.required' => 'Debes seleccionar un domiciliario.',
        ]);

        try {
            $pedido = Pedido::findOrFail($this->pedidoIdDespacho);

            if (trim((string) $pedido->estado) !== Pedido::ESTADO_EN_PREPARACION) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} ya no está disponible para despacho.",
                ]);
                return;
            }

            $usuario = Auth::user();
            $domiciliarioNuevo = Domiciliario::findOrFail($this->domiciliarioSeleccionado);

            // Si ya tenía otro domiciliario asignado y es distinto, liberarlo
            if ($pedido->domiciliario_id && $pedido->domiciliario_id !== $domiciliarioNuevo->id) {
                $domiciliarioAnterior = Domiciliario::find($pedido->domiciliario_id);

                if ($domiciliarioAnterior && $domiciliarioAnterior->estado === 'ocupado') {
                    $domiciliarioAnterior->estado = 'disponible';
                    $domiciliarioAnterior->save();
                }
            }

            $pedido->domiciliario_id = $domiciliarioNuevo->id;
            $pedido->fecha_asignacion_domiciliario = now();
            $pedido->fecha_salida_domiciliario = now();
            $pedido->save();

            $domiciliarioNuevo->estado = 'ocupado';
            $domiciliarioNuevo->save();

            $pedido->registrarHistorial(
                estadoNuevo: $pedido->estado,
                estadoAnterior: $pedido->estado,
                titulo: 'Domiciliario asignado',
                descripcion: 'Se asignó el domiciliario ' . $domiciliarioNuevo->nombre . ' al pedido.',
                usuario: $usuario?->name,
                usuarioId: $usuario?->id
            );

            $token = $pedido->generarTokenEntrega();

            $pedido->cambiarEstado(
                Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                'Tu pedido ya va en camino.',
                'Pedido en camino',
                $usuario?->name,
                $usuario?->id
            );

            $pedido->notificarTokenEntrega($token);

            $this->cerrarModalDespacho();
            $this->cargarPedidos();

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "Pedido #{$pedido->id} asignado y despachado correctamente.",
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ocurrió un error al asignar el domiciliario y despachar el pedido.',
            ]);
        }
    }

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
        $this->resetValidation();
    }

    public function confirmarEntregaConToken(): void
    {
        try {
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

            $pedido->cambiarEstado(
                Pedido::ESTADO_ENTREGADO,
                'Tu pedido fue entregado correctamente.',
                'Pedido entregado',
                $usuario?->name,
                $usuario?->id
            );

            if ($pedido->domiciliario) {
                $pedido->domiciliario->estado = 'disponible';
                $pedido->domiciliario->save();
            }

            $this->cerrarModalEntrega();
            $this->cargarPedidos();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} marcado como entregado correctamente.",
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ocurrió un error al confirmar la entrega.',
            ]);
        }
    }

    public function cancelarPedido(int $pedidoId): void
    {
        try {
            $pedido       = Pedido::findOrFail($pedidoId);
            $estadoActual = trim((string) $pedido->estado);

            if (in_array($estadoActual, [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO], true)) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} no se puede cancelar.",
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

            if ($pedido->domiciliario) {
                $pedido->domiciliario->estado = 'disponible';
                $pedido->domiciliario->save();
            }

            $this->cargarPedidos();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} cancelado.",
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ocurrió un error al cancelar el pedido.',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.pedidos.index', [
            'pedidos'          => $this->pedidos,
            'pedidosFiltrados' => $this->pedidosFiltrados,
        ])->layout('layouts.app');
    }
}