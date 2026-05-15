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
    public string $tipoEntrega = 'todos'; // 'todos' | 'domicilio' | 'recoger'

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
        'estado'      => ['except' => 'todos'],
        'zona'        => ['except' => 'todas'],
        'tipoEntrega' => ['except' => 'todos'],
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
            // Excluir pedidos de recogida — no requieren domiciliario
            if (($p->tipo_entrega ?? 'domicilio') !== 'domicilio') continue;
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

    /**
     * Colección base con filtros de zona + tipo de entrega aplicados,
     * pero SIN filtro de estado. Sirve para calcular KPIs que respondan
     * al filtro de tipo de entrega sin perder la visión por estado.
     */
    #[Computed]
    public function pedidosBase()
    {
        $pedidos = $this->pedidos;

        if ($this->zona !== 'todas') {
            if ($this->zona === 'sin_zona') {
                $pedidos = $pedidos->whereNull('zona_cobertura_id');
            } else {
                $pedidos = $pedidos->where('zona_cobertura_id', (int) $this->zona);
            }
        }

        if ($this->tipoEntrega !== 'todos') {
            $pedidos = $pedidos->filter(function ($p) {
                $tipo = $p->tipo_entrega ?? 'domicilio';
                return $tipo === $this->tipoEntrega;
            });
        }

        return $pedidos->values();
    }

    #[Computed]
    public function pedidosFiltrados()
    {
        $pedidos = $this->pedidos;

        if ($this->estado === 'programados') {
            // Filtro especial: pedidos con fecha programada futura, no entregados ni cancelados
            $pedidos = $pedidos->filter(function ($p) {
                if (empty($p->programado_para)) return false;
                if (in_array($p->estado, [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO], true)) return false;
                return true;
            });
        } elseif ($this->estado !== 'todos') {
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

        // 🚚 Filtro tipo de entrega
        if ($this->tipoEntrega !== 'todos') {
            $pedidos = $pedidos->filter(function ($p) {
                $tipo = $p->tipo_entrega ?? 'domicilio';
                return $tipo === $this->tipoEntrega;
            });
        }

        return $pedidos->values();
    }

    /**
     * 🏪 Marca un pedido de RECOGIDA como listo para que el cliente lo recoja.
     * Salta el flujo de domiciliario completo (no aplica).
     */
    public function marcarListoParaRecoger(int $pedidoId): void
    {
        try {
            $pedido = Pedido::findOrFail($pedidoId);

            if (($pedido->tipo_entrega ?? 'domicilio') !== 'recoger') {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} no es para recoger en sede.",
                ]);
                return;
            }

            if ($pedido->estado !== Pedido::ESTADO_EN_PREPARACION) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "Primero debe estar en preparación.",
                ]);
                return;
            }

            // Generar token corto para que el mostrador lo verifique al entregar
            if (empty($pedido->token_entrega)) {
                $pedido->generarTokenEntrega();
            }

            $usuario = Auth::user();
            $pedido->cambiarEstado(
                Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                'Tu pedido está listo para que pases por la sede.',
                'Listo para recoger',
                $usuario?->name,
                $usuario?->id
            );

            $this->refrescar();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} marcado como listo para recoger.",
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudo marcar como listo para recoger.',
            ]);
        }
    }

    /**
     * 🏪 Confirma que el cliente recogió el pedido en sede.
     */
    public function confirmarRecogida(int $pedidoId): void
    {
        try {
            $pedido = Pedido::findOrFail($pedidoId);

            if (($pedido->tipo_entrega ?? 'domicilio') !== 'recoger') {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "El pedido #{$pedido->id} no es para recoger en sede.",
                ]);
                return;
            }

            $usuario = Auth::user();
            $pedido->cambiarEstado(
                Pedido::ESTADO_ENTREGADO,
                'Cliente recogió el pedido en la sede.',
                'Pedido recogido por el cliente',
                $usuario?->name,
                $usuario?->id
            );

            $this->refrescar();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} marcado como recogido.",
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudo confirmar la recogida.',
            ]);
        }
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

            // ─── 1. INVALIDAR ENLACE DE PAGO WOMPI ────────────────────────
            // Mover la referencia actual al historial para que el link viejo
            // apunte a una referencia huérfana. Si llega un pago posterior,
            // el webhook lo rechazará por estado_pago = 'anulado'.
            $teniaLinkPago = false;
            if (!empty($pedido->wompi_reference)) {
                $teniaLinkPago = true;
                $historial = $pedido->wompi_referencias_historial ?? [];
                if (!in_array($pedido->wompi_reference, $historial, true)) {
                    $historial[] = $pedido->wompi_reference;
                }
                $pedido->wompi_referencias_historial = $historial;
                $pedido->wompi_reference = null;

                \Log::info('🚫 Link de pago Wompi invalidado por cancelación', [
                    'pedido_id'          => $pedido->id,
                    'ref_invalidada'     => end($historial),
                ]);
            }

            // ─── 2. MARCAR PAGO COMO ANULADO ──────────────────────────────
            // El guard del WompiWebhookController bloquea pagos en pedidos
            // con estado_pago = 'anulado', incluso si la referencia aún
            // existiese en el historial.
            if ($pedido->estado_pago !== 'aprobado') {
                $pedido->estado_pago = 'anulado';
            }

            $pedido->observacion_estado = trim(
                (string) $pedido->observacion_estado
                . ' | Cancelado por ' . ($usuario?->name ?? 'Sistema')
                . ' el ' . now()->format('Y-m-d H:i')
                . ($teniaLinkPago ? ' — Link de pago Wompi invalidado.' : '')
            );
            $pedido->saveQuietly(); // guardar antes de cambiarEstado para que los campos estén listos

            // ─── 3. CAMBIAR ESTADO + NOTIFICAR WHATSAPP ───────────────────
            $pedido->cambiarEstado(
                Pedido::ESTADO_CANCELADO,
                'Tu pedido fue cancelado.',
                'Pedido cancelado',
                $usuario?->name,
                $usuario?->id
            );

            // Liberar domiciliario
            if ($pedido->domiciliario) {
                $pedido->domiciliario->estado = 'disponible';
                $pedido->domiciliario->save();
            }

            $this->refrescar();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Pedido #{$pedido->id} cancelado."
                    . ($teniaLinkPago ? ' Link de pago Wompi invalidado.' : ''),
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
        // 🧍 Conversaciones en modo humano (handoff) — alerta al operador de pedidos
        $handoffPendientes = \App\Models\ConversacionWhatsapp::query()
            ->where('atendida_por_humano', true)
            ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA)
            ->with('cliente:id,nombre,telefono_normalizado')
            ->orderByDesc('derivada_at')
            ->limit(10)
            ->get(['id', 'cliente_id', 'departamento_id', 'derivada_at', 'updated_at', 'telefono_normalizado']);

        $handoffTotal = \App\Models\ConversacionWhatsapp::where('atendida_por_humano', true)
            ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA)
            ->count();

        // pedidos y pedidosFiltrados son #[Computed] → accesibles directamente en la vista
        return view('livewire.pedidos.index', [
            'zonasDisponibles'   => ZonaCobertura::activas()->orderBy('nombre')->get(),
            'handoffPendientes'  => $handoffPendientes,
            'handoffTotal'       => $handoffTotal,
        ])->layout('layouts.app');
    }
}