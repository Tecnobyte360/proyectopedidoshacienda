<?php

namespace App\Livewire\Despachos;

use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public ?int $sedeId = null;
    public ?int $zonaSeleccionada = null;

    /** Pedidos seleccionados para despachar (id => true) */
    public array $seleccionados = [];

    public bool $modalAbierto = false;
    public ?int $domiciliarioSeleccionado = null;
    public string $notaDespacho = '';

    protected $listeners = [
        'pedidoActualizado' => 'refrescar',
    ];

    public function refrescar(): void
    {
        // Solo dispara render
    }

    public function updatingSedeId(): void
    {
        $this->seleccionados = [];
        $this->zonaSeleccionada = null;
    }

    public function updatingZonaSeleccionada(): void
    {
        $this->seleccionados = [];
    }

    public function toggleZona(?int $zonaId): void
    {
        $this->zonaSeleccionada = $this->zonaSeleccionada === $zonaId ? null : $zonaId;
        $this->seleccionados = [];
    }

    public function seleccionarTodosDeZona(int $zonaId): void
    {
        $pedidos = $this->pedidosEnPreparacion()->where('zona_cobertura_id', $zonaId)->pluck('id');

        foreach ($pedidos as $id) {
            $this->seleccionados[$id] = true;
        }
    }

    public function limpiarSeleccion(): void
    {
        $this->seleccionados = [];
    }

    public function abrirModalDespacho(): void
    {
        $ids = collect($this->seleccionados)->filter()->keys();

        if ($ids->isEmpty()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'Selecciona al menos un pedido para despachar.',
            ]);
            return;
        }

        $this->modalAbierto = true;
        $this->domiciliarioSeleccionado = null;
        $this->notaDespacho = '';
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->domiciliarioSeleccionado = null;
        $this->notaDespacho = '';
        $this->resetValidation();
    }

    public function confirmarDespacho(): void
    {
        $this->validate([
            'domiciliarioSeleccionado' => 'required|exists:domiciliarios,id',
        ], [
            'domiciliarioSeleccionado.required' => 'Selecciona un domiciliario.',
        ]);

        $ids = collect($this->seleccionados)->filter()->keys()->all();

        if (empty($ids)) {
            $this->cerrarModal();
            return;
        }

        $domiciliario = Domiciliario::findOrFail($this->domiciliarioSeleccionado);
        $usuario      = Auth::user();

        $despachados = 0;

        DB::transaction(function () use ($ids, $domiciliario, $usuario, &$despachados) {
            $pedidos = Pedido::whereIn('id', $ids)
                ->where('estado', Pedido::ESTADO_EN_PREPARACION)
                ->get();

            foreach ($pedidos as $pedido) {
                // Liberar domiciliario anterior si lo había
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
                    titulo: 'Domiciliario asignado',
                    descripcion: "Asignado a {$domiciliario->nombre}" . ($this->notaDespacho ? " · {$this->notaDespacho}" : ''),
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

                $despachados++;
            }

            // Marcar domiciliario como ocupado
            if ($despachados > 0) {
                $domiciliario->estado = 'ocupado';
                $domiciliario->save();
            }
        });

        $this->cerrarModal();
        $this->seleccionados = [];

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✅ {$despachados} pedido(s) despachados con {$domiciliario->nombre}.",
        ]);
    }

    private function pedidosEnPreparacion()
    {
        return Pedido::query()
            ->with(['detalles', 'zonaCobertura', 'sede', 'domiciliario'])
            ->where('estado', Pedido::ESTADO_EN_PREPARACION)
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->orderBy('zona_cobertura_id')
            ->orderBy('fecha_pedido');
    }

    public function render()
    {
        $pedidos = $this->pedidosEnPreparacion()->get();

        // Agrupar por zona (los sin zona van como "Sin zona asignada")
        $agrupados = $pedidos->groupBy(function ($p) {
            return $p->zona_cobertura_id ?? 0;
        })->map(function ($grupo, $zonaId) {
            $zona = $zonaId ? $grupo->first()->zonaCobertura : null;
            return [
                'zona'    => $zona,
                'pedidos' => $grupo,
                'total'   => $grupo->sum('total'),
            ];
        });

        // Si hay filtro de zona seleccionada
        if ($this->zonaSeleccionada !== null) {
            $agrupados = $agrupados->filter(fn ($g, $k) => $k == $this->zonaSeleccionada);
        }

        // Domiciliarios disponibles (filtrados por zona si aplica)
        $domiciliarios = Domiciliario::where('activo', true)
            ->orderByRaw("CASE estado WHEN 'disponible' THEN 0 WHEN 'ocupado' THEN 1 ELSE 2 END")
            ->orderBy('nombre')
            ->get();

        // Si hay zona seleccionada en modal, sugerir domiciliarios de esa zona
        if (!empty($this->seleccionados)) {
            $zonasDePedidosSeleccionados = Pedido::whereIn('id', collect($this->seleccionados)->filter()->keys())
                ->pluck('zona_cobertura_id')
                ->filter()
                ->unique();

            if ($zonasDePedidosSeleccionados->count() === 1) {
                $zonaUnica = $zonasDePedidosSeleccionados->first();
                $domiciliarios = $domiciliarios->map(function ($d) use ($zonaUnica) {
                    $d->cubre_zona = $d->zonas->contains('id', $zonaUnica);
                    return $d;
                })->loadMissing('zonas');
            }
        }

        $sedes = Sede::orderBy('nombre')->get();

        $totalPedidos    = $pedidos->count();
        $totalSelected   = count(array_filter($this->seleccionados));
        $totalSelMonto   = Pedido::whereIn('id', collect($this->seleccionados)->filter()->keys())->sum('total');

        return view('livewire.despachos.index', compact(
            'agrupados',
            'sedes',
            'domiciliarios',
            'totalPedidos',
            'totalSelected',
            'totalSelMonto'
        ))->layout('layouts.app');
    }
}
