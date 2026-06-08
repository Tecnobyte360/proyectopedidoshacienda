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

    // Modal conversación IA
    public bool  $modalConversacionAbierto = false;
    public int   $pedidoIdConversacion     = 0;
    public array $mensajesConversacion     = [];
    public ?bool $clienteExisteErp         = null;
    public ?string $cedulaCliente          = null;

    // 🚀 DESPACHO MASIVO: selección múltiple + asignación por zona
    public array $seleccionadosMasivo = [];     // [pedido_id => true]
    public bool  $modalMasivoAbierto = false;
    public array $domiciliariosPorZonaMasivo = [];   // [zona_id => domiciliario_id]

    // ⚖️ EDITOR DE PRODUCTOS (modificar kilos/cantidad y recalcular)
    public bool  $modalProductosAbierto = false;
    public int   $pedidoIdProductos      = 0;
    public array $itemsEditar            = []; // [{id,producto,cantidad,unidad,precio_unitario,subtotal}]
    public float $costoEnvioEditar       = 0.0;

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
            // 🏢 Filtrar por sede del operador. Admin/Gerente ven todo.
            $q = Pedido::with(['detalles', 'sede', 'domiciliario', 'zonaCobertura', 'estadoPedidoBot'])
                ->latest();
            $q = \App\Support\SedeScopeFilter::aplicar($q);

            // 🗂️ PERMISOS POR ESTADO: si el usuario tiene permisos granulares,
            //    solo trae los pedidos de los estados que puede ver (seguridad
            //    real, no solo ocultar pestañas).
            $estadosPerm = $this->estadosPermitidos();
            if ($estadosPerm !== null) {
                $verProgramados = $this->puedeVerProgramados();
                $q->where(function ($qq) use ($estadosPerm, $verProgramados) {
                    if (!empty($estadosPerm)) {
                        $qq->whereIn('estado', $estadosPerm);
                    } else {
                        $qq->whereRaw('1 = 0'); // sin estados permitidos
                    }
                    if ($verProgramados) {
                        $qq->orWhereNotNull('programado_para');
                    }
                });
            }

            return $q->get();
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }

    /** Mapa permiso → estados reales que habilita. */
    private function mapaPermisoEstado(): array
    {
        return [
            'pedidos.ver-nuevos'      => [Pedido::ESTADO_NUEVO, 'confirmado'],
            'pedidos.ver-en-proceso'  => [Pedido::ESTADO_EN_PREPARACION],
            'pedidos.ver-despachados' => [Pedido::ESTADO_REPARTIDOR_EN_CAMINO, Pedido::ESTADO_RECOGIDO],
            'pedidos.ver-entregados'  => [Pedido::ESTADO_ENTREGADO],
            'pedidos.ver-cancelados'  => [Pedido::ESTADO_CANCELADO],
        ];
    }

    /** ¿El usuario tiene CONFIGURADO algún permiso granular de estado? */
    #[Computed]
    public function permisosGranulares(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        foreach (array_merge(array_keys($this->mapaPermisoEstado()), ['pedidos.ver-programados']) as $p) {
            if ($u->can($p)) return true;
        }
        return false;
    }

    /**
     * Estados que el usuario puede ver. null = sin restricción (ve todo).
     */
    private function estadosPermitidos(): ?array
    {
        if (!$this->permisosGranulares()) return null; // compat: ve todo
        $u = auth()->user();
        $estados = [];
        foreach ($this->mapaPermisoEstado() as $perm => $sts) {
            if ($u->can($perm)) $estados = array_merge($estados, $sts);
        }
        return array_values(array_unique($estados));
    }

    public function puedeVerProgramados(): bool
    {
        if (!$this->permisosGranulares()) return true;
        return auth()->user()?->can('pedidos.ver-programados') ?? false;
    }

    /** ¿Se debe mostrar la pestaña con esta key? */
    public function tabPermitida(string $key): bool
    {
        if (!$this->permisosGranulares()) return true; // ve todas
        $u = auth()->user();
        return match ($key) {
            'todos'                              => true,
            Pedido::ESTADO_NUEVO                 => (bool) $u?->can('pedidos.ver-nuevos'),
            'programados'                        => (bool) $u?->can('pedidos.ver-programados'),
            Pedido::ESTADO_EN_PREPARACION        => (bool) $u?->can('pedidos.ver-en-proceso'),
            Pedido::ESTADO_REPARTIDOR_EN_CAMINO  => (bool) $u?->can('pedidos.ver-despachados'),
            Pedido::ESTADO_ENTREGADO             => (bool) $u?->can('pedidos.ver-entregados'),
            Pedido::ESTADO_CANCELADO             => (bool) $u?->can('pedidos.ver-cancelados'),
            default                              => true,
        };
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

    /**
     * Abre modal con el resumen de conversación IA del pedido + estado SGI.
     */
    public function verConversacion(int $pedidoId): void
    {
        $pedido = Pedido::find($pedidoId);
        if (!$pedido) return;

        $this->pedidoIdConversacion = $pedidoId;
        $this->mensajesConversacion = [];
        $this->clienteExisteErp     = null;
        $this->cedulaCliente        = null;

        // 1. Intentar cargar mensajes desde la relación conversacionWhatsapp
        $conv = \App\Models\ConversacionWhatsapp::where('pedido_id', $pedidoId)->first();
        if ($conv) {
            $this->mensajesConversacion = $conv->mensajes()
                ->whereIn('rol', ['user', 'assistant'])
                ->where('tipo', 'text')
                ->whereNotNull('contenido')
                ->where('contenido', '!=', '')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(100)
                ->get()
                ->map(fn ($m) => [
                    'rol'       => $m->rol,
                    'contenido' => $m->contenido,
                    'hora'      => $m->created_at?->format('h:i a'),
                ])
                ->toArray();

            // 2. Obtener estado ERP desde ConversacionPedidoEstado
            $estado = \App\Models\ConversacionPedidoEstado::where('conversacion_id', $conv->id)->first();
            if ($estado) {
                $this->clienteExisteErp = $estado->cliente_existe_erp;
                $this->cedulaCliente    = $estado->cedula;
            }
        }

        // 3. Fallback: si no hay mensajes en BD, intentar desde conversacion_completa JSON
        if (empty($this->mensajesConversacion) && !empty($pedido->conversacion_completa)) {
            $historial = json_decode($pedido->conversacion_completa, true) ?? [];
            $this->mensajesConversacion = collect($historial)
                ->filter(fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant']) && !empty($m['content'] ?? ''))
                ->map(fn ($m) => [
                    'rol'       => $m['role'],
                    'contenido' => is_string($m['content']) ? $m['content'] : json_encode($m['content']),
                    'hora'      => null,
                ])
                ->values()
                ->toArray();
        }

        $this->modalConversacionAbierto = true;
    }

    public function cerrarModalConversacion(): void
    {
        $this->modalConversacionAbierto = false;
        $this->pedidoIdConversacion     = 0;
        $this->mensajesConversacion     = [];
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

    /**
     * ⚖️ Abre el editor de productos de un pedido: carga sus líneas para
     * poder modificar la cantidad/kilos y recalcular el valor.
     */
    public function abrirEditorProductos(int $pedidoId): void
    {
        $pedido = Pedido::with('detalles')->find($pedidoId);
        if (!$pedido) return;

        // No permitir editar pedidos ya cerrados.
        if (in_array(trim((string) $pedido->estado), [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO], true)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Este pedido ya está cerrado, no se puede editar.']);
            return;
        }

        $this->pedidoIdProductos = $pedido->id;
        $this->costoEnvioEditar  = (float) ($pedido->costo_envio ?? 0);
        $this->itemsEditar = $pedido->detalles->map(fn ($d) => [
            'id'              => $d->id,
            'producto'        => $d->producto,
            'unidad'          => $d->unidad ?: 'und',
            'precio_unitario' => (float) ($d->precio_unitario ?? 0),
            'cantidad'        => (float) ($d->cantidad ?? 1),
            'subtotal'        => (float) ($d->subtotal ?? 0),
        ])->values()->all();

        $this->modalProductosAbierto = true;
    }

    public function cerrarEditorProductos(): void
    {
        $this->modalProductosAbierto = false;
        $this->reset(['pedidoIdProductos', 'itemsEditar', 'costoEnvioEditar']);
    }

    /** Recalcula el subtotal de una línea cuando cambia la cantidad. */
    public function updatedItemsEditar($value, $key): void
    {
        // $key viene como "0.cantidad" → recalcular esa línea.
        [$idx] = explode('.', $key);
        if (isset($this->itemsEditar[$idx])) {
            $cant   = (float) ($this->itemsEditar[$idx]['cantidad'] ?? 0);
            $precio = (float) ($this->itemsEditar[$idx]['precio_unitario'] ?? 0);
            $this->itemsEditar[$idx]['subtotal'] = round($cant * $precio, 2);
        }
    }

    /** Total recalculado en vivo (suma de líneas + envío). */
    public function getTotalEditarProperty(): float
    {
        $sub = collect($this->itemsEditar)->sum(fn ($i) => (float) ($i['subtotal'] ?? 0));
        return round($sub + (float) $this->costoEnvioEditar, 2);
    }

    /** Guarda los cambios: persiste cada línea y recalcula subtotal/total del pedido. */
    public function guardarProductos(): void
    {
        $pedido = Pedido::with('detalles')->find($this->pedidoIdProductos);
        if (!$pedido) return;

        try {
            $detallesPorId = $pedido->detalles->keyBy('id');
            $subtotal = 0.0;

            foreach ($this->itemsEditar as $item) {
                $cant   = max(0, (float) ($item['cantidad'] ?? 0));
                $precio = (float) ($item['precio_unitario'] ?? 0);
                $sub    = round($cant * $precio, 2);
                $subtotal += $sub;

                $d = $detallesPorId->get($item['id']);
                if ($d) {
                    $d->cantidad = $cant;
                    $d->subtotal = $sub;
                    $d->save();
                }
            }

            // Recalcular subtotal y total del pedido (subtotal + envío).
            $pedido->subtotal = round($subtotal, 2);
            $pedido->total    = round($subtotal + (float) ($pedido->costo_envio ?? 0), 2);
            $pedido->save();

            $this->dispatch('notify', ['type' => 'success', 'message' => "Pedido #{$pedido->id} actualizado. Nuevo total: $" . number_format($pedido->total, 0, ',', '.')]);
            $this->cerrarEditorProductos();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Editor productos: guardar falló', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo guardar: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        // 🧍 Conversaciones en modo humano (handoff) — alerta al operador de pedidos
        //    Filtradas por sede del user
        $qHandoff = \App\Models\ConversacionWhatsapp::query()
            ->where('atendida_por_humano', true)
            ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA);
        $qHandoff = \App\Support\SedeScopeFilter::aplicar($qHandoff);

        $handoffPendientes = (clone $qHandoff)
            ->with('cliente:id,nombre,telefono_normalizado')
            ->orderByDesc('derivada_at')
            ->limit(10)
            ->get(['id', 'cliente_id', 'departamento_id', 'derivada_at', 'updated_at', 'telefono_normalizado', 'sede_id']);

        $handoffTotal = $qHandoff->count();

        // pedidos y pedidosFiltrados son #[Computed] → accesibles directamente en la vista
        return view('livewire.pedidos.index', [
            'zonasDisponibles'   => ZonaCobertura::activas()->orderBy('nombre')->get(),
            'handoffPendientes'  => $handoffPendientes,
            'handoffTotal'       => $handoffTotal,
        ])->layout('layouts.app');
    }
}