<?php

namespace App\Livewire\Domiciliarios;

use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Sede;
use App\Services\RutaOptimizadaService;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Portal personal del domiciliario — vista pública (sin auth) accesible
 * por el link único basado en token. El domiciliario lo abre desde su
 * celular y ve sus pedidos asignados, puede marcar como entregado y
 * abrir la ruta optimizada en Google Maps.
 */
class Portal extends Component
{
    public Domiciliario $domiciliario;
    public ?float $origenLat = null;
    public ?float $origenLng = null;

    public function mount(string $token): void
    {
        $domi = Domiciliario::withoutGlobalScopes()
            ->where('token_acceso', $token)
            ->firstOrFail();

        $this->domiciliario = $domi;

        // Activar el contexto del tenant para que las queries respeten scope
        if ($domi->tenant_id) {
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($domi->tenant_id);
            if ($tenant) {
                app(\App\Services\TenantManager::class)->set($tenant);
            }
        }

        // Punto de partida: ubicación actual del domi (si la reportó) o sede principal
        $this->origenLat = $domi->lat_actual;
        $this->origenLng = $domi->lng_actual;
        if (!$this->origenLat || !$this->origenLng) {
            $sede = Sede::where('tenant_id', $domi->tenant_id)
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->first();
            if ($sede) {
                $this->origenLat = (float) $sede->latitud;
                $this->origenLng = (float) $sede->longitud;
            }
        }
    }

    #[Computed]
    public function pedidosActivos()
    {
        return Pedido::where('domiciliario_id', $this->domiciliario->id)
            ->whereNotIn('estado', [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO])
            ->with('sede:id,nombre')
            ->orderBy('fecha_pedido')
            ->get();
    }

    #[Computed]
    public function pedidosOrdenados()
    {
        return app(RutaOptimizadaService::class)
            ->optimizar($this->pedidosActivos, $this->origenLat, $this->origenLng);
    }

    #[Computed]
    public function urlRutaOptima(): ?string
    {
        return app(RutaOptimizadaService::class)
            ->urlGoogleMaps($this->pedidosOrdenados, $this->origenLat, $this->origenLng);
    }

    #[Computed]
    public function totalPedidosHoy(): int
    {
        return Pedido::where('domiciliario_id', $this->domiciliario->id)
            ->whereDate('fecha_asignacion_domiciliario', now()->toDateString())
            ->count();
    }

    #[Computed]
    public function entregadosHoy(): int
    {
        return Pedido::where('domiciliario_id', $this->domiciliario->id)
            ->whereDate('fecha_entregado', now()->toDateString())
            ->count();
    }

    public function marcarEnCamino(int $pedidoId): void
    {
        $pedido = Pedido::where('domiciliario_id', $this->domiciliario->id)
            ->where('id', $pedidoId)
            ->firstOrFail();

        if ($pedido->estado === Pedido::ESTADO_REPARTIDOR_EN_CAMINO) return;

        $pedido->cambiarEstado(
            Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
            "Marcado en camino por {$this->domiciliario->nombre}",
            'En camino'
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => '🛵 Pedido en camino']);
    }

    public function marcarEntregado(int $pedidoId): void
    {
        $pedido = Pedido::where('domiciliario_id', $this->domiciliario->id)
            ->where('id', $pedidoId)
            ->firstOrFail();

        if ($pedido->estado === Pedido::ESTADO_ENTREGADO) return;

        $pedido->cambiarEstado(
            Pedido::ESTADO_ENTREGADO,
            "Entregado por {$this->domiciliario->nombre}",
            'Entregado'
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Pedido entregado']);
    }

    public function actualizarMiUbicacion(float $lat, float $lng): void
    {
        $this->domiciliario->update([
            'lat_actual' => $lat,
            'lng_actual' => $lng,
            'ubicacion_actualizada_at' => now(),
        ]);
        $this->origenLat = $lat;
        $this->origenLng = $lng;
    }

    public function render()
    {
        return view('livewire.domiciliarios.portal')->layout('layouts.public');
    }
}
