<?php

namespace App\Livewire\Rutas;

use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Sede;
use App\Services\RutaOptimizadaService;
use Livewire\Component;

/**
 * Vista admin /rutas — muestra a todos los domiciliarios con sus pedidos
 * activos en el orden óptimo de entrega y un mapa con la ruta sugerida.
 */
class Index extends Component
{
    public string $busqueda = '';
    public ?int $domiExpandido = null;   // null = todos colapsados, id = ese expandido

    public function expandir(int $domiId): void
    {
        $this->domiExpandido = $this->domiExpandido === $domiId ? null : $domiId;
    }

    public function asignarPedido(int $pedidoId, int $domiId): void
    {
        $pedido = Pedido::find($pedidoId);
        $domi   = Domiciliario::find($domiId);
        if (!$pedido || !$domi) return;

        $pedido->domiciliario_id = $domi->id;
        $pedido->fecha_asignacion_domiciliario = now();
        $pedido->saveQuietly();

        if ($domi->estado === Domiciliario::ESTADO_DISPONIBLE) {
            $domi->update(['estado' => Domiciliario::ESTADO_EN_RUTA]);
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => "✅ Pedido #{$pedido->id} asignado a {$domi->nombre}"]);
    }

    public function liberarPedido(int $pedidoId): void
    {
        $pedido = Pedido::find($pedidoId);
        if (!$pedido) return;
        $pedido->domiciliario_id = null;
        $pedido->fecha_asignacion_domiciliario = null;
        $pedido->saveQuietly();
        $this->dispatch('notify', ['type' => 'info', 'message' => 'Pedido liberado.']);
    }

    /**
     * Auto-asigna domiciliarios a TODOS los pedidos sin asignar usando el
     * criterio configurado. Útil cuando hay pedidos huérfanos o cuando el
     * tenant acaba de activar la auto-asignación y quiere retroactiva.
     */
    public function autoAsignarPendientes(): void
    {
        $sinAsignar = Pedido::query()
            ->whereNull('domiciliario_id')
            ->whereIn('estado', [Pedido::ESTADO_NUEVO, Pedido::ESTADO_EN_PREPARACION])
            ->get();

        if ($sinAsignar->isEmpty()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No hay pedidos sin asignar.']);
            return;
        }

        $service = app(\App\Services\AsignacionDomiciliarioService::class);
        $asignados = 0;
        $sinDomis = 0;

        foreach ($sinAsignar as $pedido) {
            // Forzar asignación temporal incluso si el toggle está off
            $cfg = \App\Models\ConfiguracionBot::actual();
            $original = $cfg->auto_asignar_domiciliario;
            $cfg->auto_asignar_domiciliario = true;
            // No persistir el cambio del toggle
            $r = $service->asignar($pedido);
            $cfg->auto_asignar_domiciliario = $original;

            if ($r) {
                $asignados++;
            } else {
                $sinDomis++;
            }
        }

        $msg = "✅ {$asignados} pedido(s) asignados.";
        if ($sinDomis > 0) {
            $msg .= " {$sinDomis} sin asignar (no hay domiciliarios disponibles).";
        }

        $this->dispatch('notify', [
            'type'    => $asignados > 0 ? 'success' : 'warning',
            'message' => $msg,
        ]);
    }

    public function render()
    {
        $domiciliarios = Domiciliario::query()
            ->where('activo', true)
            ->when($this->busqueda !== '', function ($q) {
                $q->where(function ($qq) {
                    $b = '%' . $this->busqueda . '%';
                    $qq->where('nombre', 'like', $b)
                       ->orWhere('telefono', 'like', $b)
                       ->orWhere('vehiculo', 'like', $b)
                       ->orWhere('placa', 'like', $b);
                });
            })
            ->orderBy('nombre')
            ->get();

        // Cargar pedidos activos por domiciliario (con eager loading)
        $pedidosPorDomi = Pedido::query()
            ->whereIn('domiciliario_id', $domiciliarios->pluck('id'))
            ->whereNotIn('estado', [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO])
            ->with('sede:id,nombre')
            ->orderBy('fecha_pedido')
            ->get()
            ->groupBy('domiciliario_id');

        // Pedidos sin asignar (para drag/asignar manual)
        $sinAsignar = Pedido::query()
            ->whereNull('domiciliario_id')
            ->whereIn('estado', [Pedido::ESTADO_NUEVO, Pedido::ESTADO_EN_PREPARACION])
            ->orderBy('fecha_pedido')
            ->take(50)
            ->get();

        // Sede principal para usar como origen de ruta si el domi no tiene ubicación
        $sedePrincipal = Sede::whereNotNull('latitud')->whereNotNull('longitud')->first();

        $optimizador = app(RutaOptimizadaService::class);

        // Pre-calcular ruta optimizada y URL maps por domi
        $rutas = [];
        foreach ($domiciliarios as $domi) {
            $pedidosDomi = $pedidosPorDomi->get($domi->id, collect());
            $origenLat = $domi->lat_actual ?: ($sedePrincipal->latitud ?? null);
            $origenLng = $domi->lng_actual ?: ($sedePrincipal->longitud ?? null);

            $ordenados = $optimizador->optimizar($pedidosDomi, $origenLat, $origenLng);
            $url = $optimizador->urlGoogleMaps($ordenados, $origenLat, $origenLng);

            $rutas[$domi->id] = [
                'pedidos'    => $ordenados,
                'url_maps'   => $url,
                'total_pedidos' => $pedidosDomi->count(),
                'origen_lat' => $origenLat,
                'origen_lng' => $origenLng,
            ];
        }

        return view('livewire.rutas.index', [
            'domiciliarios' => $domiciliarios,
            'rutas'         => $rutas,
            'sinAsignar'    => $sinAsignar,
        ])->layout('layouts.app');
    }
}
