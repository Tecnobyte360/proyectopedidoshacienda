<?php

namespace App\Livewire\Zonas;

use App\Models\ZonaCobertura;
use App\Services\TenantManager;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EditorMapa extends Component
{
    public ZonaCobertura $zona;

    public ?array $poligono   = null;
    public ?float $centro_lat = null;
    public ?float $centro_lng = null;
    public ?float $area_km2   = null;

    public string $color  = '#d68643';
    public string $nombre = '';

    public function mount(ZonaCobertura $zona): void
    {
        $this->zona = $zona;
        $this->nombre     = $this->zona->nombre;
        $this->color      = $this->zona->color ?: '#d68643';
        $this->poligono   = $this->zona->poligono;
        $this->centro_lat = $this->zona->centro_lat;
        $this->centro_lng = $this->zona->centro_lng;
        $this->area_km2   = $this->zona->area_km2;
    }

    /**
     * Recibe el polígono dibujado en Google Maps desde JS.
     */
    public function actualizarPoligono(array $data): void
    {
        $this->poligono   = $data['coordinates'] ?? null;
        $this->centro_lat = isset($data['center']['lat']) ? (float) $data['center']['lat'] : null;
        $this->centro_lng = isset($data['center']['lng']) ? (float) $data['center']['lng'] : null;
        $this->area_km2   = isset($data['area_km2']) ? (float) $data['area_km2'] : null;
    }

    public function guardar(): void
    {
        if (!$this->poligono || count($this->poligono) < 3) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Dibuja un polígono primero (mínimo 3 puntos).']);
            return;
        }

        $this->zona->update([
            'poligono'   => $this->poligono,
            'centro_lat' => $this->centro_lat,
            'centro_lng' => $this->centro_lng,
            'area_km2'   => $this->area_km2,
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Zona guardada con el nuevo polígono.']);
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->current();
        $gmapsActivo = $tenant && $tenant->google_maps_activo && !empty($tenant->google_maps_api_key);

        $config = [
            'api_key'    => $gmapsActivo ? $tenant->google_maps_api_key : null,
            'centro_lat' => $tenant?->google_maps_centro_lat ?: 6.3414,
            'centro_lng' => $tenant?->google_maps_centro_lng ?: -75.5538,
            'zoom'       => $tenant?->google_maps_zoom ?: 13,
        ];

        return view('livewire.zonas.editor-mapa', compact('config', 'gmapsActivo'));
    }
}
