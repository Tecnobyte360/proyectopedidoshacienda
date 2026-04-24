<?php

namespace App\Livewire\Pedidos;

use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\ZonaCobertura;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public $domiciliarios = [];

    public string $estado = 'todos';
    public string $zona   = 'todas';   // 'todas' | id de zona | 'sin_zona'

    // Modal token entrega
    public bool   $modalTokenAbierto  = false;
    public int    $pedidoIdEntregando = 0;
    public string $tokenIngresado     = '';
    public string $tokenError         = '';

    // Modal despacho con domiciliario (individual)
    public bool $modalDespachoAbierto = false;
    public int $pedidoIdDespacho = 0;
    public ?int $domiciliarioSeleccionado = null;

    // 🚀 DESPACHO MASIVO: selección múltiple + asignación por zona
    public array $seleccionadosMasivo = [];     // [pedido_id => true]
    public bool  $modalMasivoAbierto = false;
    public array $domiciliariosPorZonaMasivo = [];   // [zona_id => domiciliario_id]

    /**
     * Livewire 3 — formato `echo:CANAL,NOMBRE_EVENTO`.
     * Cuando el event class usa `broadcastAs('pedido.confirmado')`,
     * el listener debe llevar el punto de prefijo (`.pedido.confirmado`).
     */
    public function getListeners(): array
    {
        $tid = app(\App\Services\TenantManager::class)->id() ?? 'global';
        return [
            "echo:pedidos.tenant.{$tid},.pedido.confirmado"  => 'onPedidoConfirmado',
            "echo:pedidos.tenant.{$tid},.pedido.actualizado" => 'onPedidoActualizado',
            'pedidoActualizado' => 'refrescar',   // evento local legacy
        ];
    }

    public function onPedidoConfirmado($event = null): void
    {
        $this->refrescar();

        $nombre = $event['cliente_nombre'] ?? 'cliente';
        $this->dispatch('nuevo-pedido-en-vivo', cliente: $nombre);
    }

    public function onPedidoActualizado($event = null): void
    {
        $this->refrescar();

        $id = $event['id'] ?? null;
        $estado = $event['estado'] ?? null;
        $this->dispatch('pedido-actualizado-en-vivo', id: $id, estado: $estado);
    }

    protected $queryString = [
        'estado' => ['except' => 'todos'],
        'zona'   => ['except' => 'todas'],
    ];

    public function refrescar(): void
    {
        // Limpia el cache de la propiedad computed para forzar recarga
        unset($this->pedidos);
    }

    /*
    |==========================================================================
    | 🚀 DESPACHO MASIVO (selección múltiple + asignación por zona)
    |==========================================================================
    */

    public function toggleSeleccionMasiva(int $pedidoId): void
    {
        if (isset($this->seleccionadosMasivo[$pedidoId])) {
            unset($this->seleccionadosMasivo[$pedidoId]);
        } else {
            $this->seleccionadosMasivo[$pedidoId] = true;
        }
    }

    public function seleccionarTodosVisibles(): void
    {
        // Solo pedidos en estado "en_preparacion" o "nuevo" se pueden despachar
        $estadosDespachables = [Pedido::ESTADO_NUEVO, Pedido::ESTADO_EN_PREPARACION, 'confirmado'];

        foreach ($this->pedidosFiltrados as $p) {
            if (in_array($p->estado, $estadosDespachables, true)) {
                $this->seleccionadosMasivo[$p->id] = true;
            }
        }
    }

    public function limpiarSeleccionMasiva(): void
    {
        $this->seleccionadosMasivo = [];
    }

    public function abrirModalMasivo(): void
    {
        $ids = collect($this->seleccionadosMasivo)->filter()->keys();
        if ($ids->isEmpty()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Selecciona al menos un pedido.']);
            return;
        }
        $this->modalMasivoAbierto = true;
        $this->domiciliariosPorZonaMasivo = [];
    }

    public function cerrarModalMasivo(): void
    {
        $this->modalMasivoAbierto = false;
        $this->domiciliariosPorZonaMasivo = [];
    }

    /**
     * Pedidos seleccionados agrupados por zona, para el modal masivo.
     */
    public function getSeleccionadosMasivoPorZonaProperty()
    {
        $ids = collect($this->seleccionadosMasivo)->filter()->keys();
        if ($ids->isEmpty()) return collect();

        return Pedido::with('zonaCobertura')
            ->whereIn('id', $ids)
            ->get()
            ->groupBy(fn ($p) => $p->zona_cobertura_id ?? 0)
            ->map(fn ($grupo, $zonaId) => [
                'zona_id' => $zonaId ?: null,
                'zona'    => $grupo->first()->zonaCobertura,
                'pedidos' => $grupo,
                'total'   => $grupo->sum('total'),
            ]);
    }

    public function confirmarDespachoMasivo(): void
    {
        $grupos = $this->seleccionadosMasivoPorZona;

        if ($grupos->isEmpty()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No hay pedidos seleccionados.']);
            return;
        }

        // Validar domiciliario por cada zona
        $faltantes = [];
        foreach ($grupos as $zonaId => $grupo) {
            $key = $zonaId ?: 0;
            if (empty($this->domiciliariosPorZonaMasivo[$key])) {
                $faltantes[] = $grupo['zona']?->nombre ?? 'Sin zona';
            }
        }

        if (!empty($faltantes)) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ Asigna domiciliario para: ' . implode(', ', $faltantes),
            ]);
            return;
        }

        $usuario = Auth::user();
        $totalDespachados = 0;
        $zonasDespachadas = 0;

        \DB::transaction(function () use ($grupos, $usuario, &$totalDespachados, &$zonasDespachadas) {
            foreach ($grupos as $zonaId => $grupo) {
                $key = $zonaId ?: 0;
                $domId = $this->domiciliariosPorZonaMasivo[$key] ?? null;
                if (!$domId) continue;

                $domiciliario = Domiciliario::find($domId);
                if (!$domiciliario) continue;

                // Pasar primero los "nuevos" a "en_preparacion" para poder despacharlos
                foreach ($grupo['pedidos'] as $pedido) {
                    if ($pedido->estado === Pedido::ESTADO_NUEVO || $pedido->estado === 'confirmado') {
                        $pedido->cambiarEstado(
                            Pedido::ESTADO_EN_PREPARACION,
                            'Pedido en preparación (auto antes de despacho masivo)',
                            'En preparación',
                            $usuario?->name,
                            $usuario?->id
                        );
                        $pedido->refresh();
                    }
                }

                foreach ($grupo['pedidos'] as $pedido) {
                    if ($pedido->estado !== Pedido::ESTADO_EN_PREPARACION) continue;

                    if ($pedido->domiciliario_id && $pedido->domiciliario_id !== $domiciliario->id) {
                        $anterior = Domiciliario::find($pedido->domiciliario_id);
                        if ($anterior && $anterior->estado === 'ocupado') {
                            $anterior->estado = 'disponible';
                            $anterior->save();
                        }
                    }

                    $pedido->domiciliario_id = $domiciliario->id;
                    $pedido->fecha_asignacion_domiciliario = now();
                    $pedido->fecha_salida_domiciliario     = now();
                    $pedido->save();

                    $pedido->registrarHistorial(
                        estadoNuevo: $pedido->estado,
                        estadoAnterior: $pedido->estado,
                        titulo: 'Domiciliario asignado (despacho masivo)',
                        descripcion: "Asignado a {$domiciliario->nombre}",
                        usuario: $usuario?->name,
                        usuarioId: $usuario?->id
                    );

                    $token = $pedido->generarTokenEntrega();

                    $pedido->cambiarEstado(
                        Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                        "Tu pedido va en camino con {$domiciliario->nombre}.",
                        'Pedido en camino',
                        $usuario?->name,
                        $usuario?->id
                    );

                    $pedido->notificarTokenEntrega($token);
                    $totalDespachados++;
                }

                $domiciliario->estado = 'ocupado';
                $domiciliario->save();
                $zonasDespachadas++;
            }
        });

        $this->cerrarModalMasivo();
        $this->seleccionadosMasivo = [];
        unset($this->pedidos);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✅ {$totalDespachados} pedido(s) despachados en {$zonasDespachadas} zona(s).",
        ]);
    }

    public function cambiarTab(string $estado): void
    {
        $this->estado = $estado;
    }

    public function updatedZona(): void {}

    #[Computed]
    public function pedidos()
    {
        try {
            return Pedido::with(['detalles', 'sede', 'domiciliario', 'zonaCobertura'])
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }

    #[Computed]
    public function pedidosFiltrados()
    {
        $pedidos = $this->pedidos;

        if ($this->estado !== 'todos') {
            // 'confirmado' es estado legacy → se trata como 'nuevo'
            $estadosBuscar = $this->estado === Pedido::ESTADO_NUEVO
                ? [Pedido::ESTADO_NUEVO, 'confirmado']
                : [$this->estado];

            $pedidos = $pedidos->whereIn('estado', $estadosBuscar);
        }

        if ($this->zona !== 'todas') {
            if ($this->zona === 'sin_zona') {
                $pedidos = $pedidos->whereNull('zona_cobertura_id');
            } else {
                $pedidos = $pedidos->where('zona_cobertura_id', (int) $this->zona);
            }
        }

        return $pedidos->values();
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

            $this->refrescar();

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
            $this->refrescar();

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
            $this->refrescar();

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

            $this->refrescar();

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
        // pedidos y pedidosFiltrados son #[Computed] → accesibles directamente en la vista
        return view('livewire.pedidos.index', [
            'zonasDisponibles' => ZonaCobertura::activas()->orderBy('nombre')->get(),
        ])->layout('layouts.app');
    }
}