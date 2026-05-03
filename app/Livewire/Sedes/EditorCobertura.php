<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use App\Services\TenantManager;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EditorCobertura extends Component
{
    public Sede $sede;

    public ?array $cobertura_poligono   = null;
    public ?float $cobertura_centro_lat = null;
    public ?float $cobertura_centro_lng = null;
    public ?float $area_km2             = null;

    public string $color = '#d68643';
    public string $nombre = '';

    public function mount(Sede $sede): void
    {
        $this->sede = $sede;
        $this->nombre  = $sede->nombre;
        $this->color   = $sede->cobertura_color ?: '#d68643';
        $this->cobertura_poligono   = $sede->cobertura_poligono;
        $this->cobertura_centro_lat = $sede->cobertura_centro_lat ?: $sede->latitud;
        $this->cobertura_centro_lng = $sede->cobertura_centro_lng ?: $sede->longitud;
    }

    public function actualizarPoligono(array $data): void
    {
        $this->cobertura_poligono   = $data['coordinates'] ?? null;
        $this->cobertura_centro_lat = isset($data['center']['lat']) ? (float) $data['center']['lat'] : null;
        $this->cobertura_centro_lng = isset($data['center']['lng']) ? (float) $data['center']['lng'] : null;
        $this->area_km2 = isset($data['area_km2']) ? (float) $data['area_km2'] : null;
    }

    public function guardar(): void
    {
        if (!$this->cobertura_poligono || count($this->cobertura_poligono) < 3) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Dibuja un polígono primero (mínimo 3 puntos).']);
            return;
        }

        $this->sede->update([
            'cobertura_poligono'   => $this->cobertura_poligono,
            'cobertura_centro_lat' => $this->cobertura_centro_lat,
            'cobertura_centro_lng' => $this->cobertura_centro_lng,
            'cobertura_activa'     => true,
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Cobertura de la sede guardada.']);
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->current();
        $gmapsActivo = $tenant && $tenant->google_maps_activo && !empty($tenant->google_maps_api_key);

        $config = [
            'api_key'    => $gmapsActivo ? $tenant->google_maps_api_key : null,
            'centro_lat' => $this->cobertura_centro_lat ?: ($tenant?->google_maps_centro_lat ?: 6.3414),
            'centro_lng' => $this->cobertura_centro_lng ?: ($tenant?->google_maps_centro_lng ?: -75.5538),
            'zoom'       => $tenant?->google_maps_zoom ?: 13,
        ];

        return view('livewire.sedes.editor-cobertura', compact('config', 'gmapsActivo'));
    }
}
