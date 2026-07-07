<?php

namespace App\Livewire\Domiciliarios;

use App\Models\Domiciliario;
use App\Services\TenantManager;
use Livewire\Component;

/**
 * 🗺️ Mapa en vivo de domiciliarios
 *
 * Muestra Google Maps con un marker por cada domiciliario activo del tenant.
 * Los markers se mueven en tiempo real cuando los domiciliarios actualizan
 * su ubicación desde el portal (vía evento Reverb `domiciliario.ubicacion`).
 */
class Mapa extends Component
{
    public ?string $filtroEstado = null;
    public bool $mostrarSoloActivos = true;

    public function getDomiciliariosProperty(): array
    {
        $q = Domiciliario::query()
            ->whereNotNull('lat_actual')
            ->whereNotNull('lng_actual');

        if ($this->mostrarSoloActivos) {
            $q->where('activo', true);
        }
        if ($this->filtroEstado) {
            $q->where('estado', $this->filtroEstado);
        }

        return $q->with(['pedidos' => function ($q) {
                $q->whereIn('estado', ['en_camino', 'entregando', 'asignado', 'preparando'])
                  ->with('sede:id,nombre,latitud,longitud')
                  ->orderByDesc('id')
                  ->limit(1);
            }])
            ->get([
                'id', 'nombre', 'telefono', 'lat_actual', 'lng_actual',
                'estado', 'vehiculo', 'placa', 'ubicacion_actualizada_at', 'activo',
            ])->map(function ($d) {
                $pedidoActivo = $d->pedidos->first();
                $ruta = null;
                if ($pedidoActivo && $pedidoActivo->lat && $pedidoActivo->lng) {
                    $ruta = [
                        'pedido_id' => $pedidoActivo->id,
                        'cliente'   => $pedidoActivo->nombre_cliente ?? $pedidoActivo->customer_name ?? 'Cliente',
                        'direccion' => $pedidoActivo->direccion,
                        'estado'    => $pedidoActivo->estado,
                        'destino_lat' => (float) $pedidoActivo->lat,
                        'destino_lng' => (float) $pedidoActivo->lng,
                        'origen_lat' => $pedidoActivo->sede?->latitud ? (float) $pedidoActivo->sede->latitud : null,
                        'origen_lng' => $pedidoActivo->sede?->longitud ? (float) $pedidoActivo->sede->longitud : null,
                        'origen_nombre' => $pedidoActivo->sede?->nombre ?? 'Sede',
                    ];
                }
                return [
                    'id'        => $d->id,
                    'nombre'    => $d->nombre,
                    'lat'       => (float) $d->lat_actual,
                    'lng'       => (float) $d->lng_actual,
                    'estado'    => $d->estado,
                    'vehiculo'  => $d->vehiculo,
                    'placa'     => $d->placa,
                    'telefono'  => $d->telefono,
                    'updated_at'=> optional($d->ubicacion_actualizada_at)->toIso8601String(),
                    'minutos_inactivo' => $d->ubicacion_actualizada_at
                        ? now()->diffInMinutes($d->ubicacion_actualizada_at)
                        : null,
                    'ruta' => $ruta,
                ];
            })->toArray();
    }

    public function getGmapsConfigProperty(): array
    {
        $tenant = app(TenantManager::class)->current();
        $apiKey = $tenant?->google_maps_api_key;
        return [
            'api_key'    => $apiKey ?: null,
            'activo'     => $tenant && $tenant->google_maps_activo && !empty($apiKey),
            'centro_lat' => (float) ($tenant?->google_maps_centro_lat ?: 6.3414),
            'centro_lng' => (float) ($tenant?->google_maps_centro_lng ?: -75.5538),
            'zoom'       => (int) ($tenant?->google_maps_zoom ?: 12),
        ];
    }

    public function render()
    {
        $tenantId = app(TenantManager::class)->id() ?? 0;
        return view('livewire.domiciliarios.mapa', [
            'domiciliarios' => $this->domiciliarios,
            'gmapsCfg'      => $this->gmapsConfig,
            'tenantId'      => $tenantId,
        ])->layout('layouts.app');
    }
}
