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

    /**
     * Recibe los polígonos desde el editor.
     * Acepta:
     *   - 'polygons' => [[[lat,lng]...], [[lat,lng]...]]  (multi-zona, nuevo)
     *   - 'coordinates' => [[lat,lng]...]                  (legacy, una sola zona)
     */
    public function actualizarPoligono(array $data): void
    {
        if (isset($data['polygons']) && is_array($data['polygons']) && count($data['polygons']) > 0) {
            // Filtrar polígonos válidos (mínimo 3 puntos)
            $polys = array_values(array_filter(
                $data['polygons'],
                fn ($p) => is_array($p) && count($p) >= 3
            ));
            $this->cobertura_poligono = count($polys) > 0 ? $polys : null;
        } else {
            // Legacy: una sola zona — la envolvemos como multi para uniformidad
            $coords = $data['coordinates'] ?? null;
            $this->cobertura_poligono = (is_array($coords) && count($coords) >= 3) ? [$coords] : null;
        }

        $this->cobertura_centro_lat = isset($data['center']['lat']) ? (float) $data['center']['lat'] : null;
        $this->cobertura_centro_lng = isset($data['center']['lng']) ? (float) $data['center']['lng'] : null;
        $this->area_km2 = isset($data['area_km2']) ? (float) $data['area_km2'] : null;
    }

    public function guardar(): void
    {
        // Validar que al menos un polígono tenga 3+ puntos
        $polys = $this->cobertura_poligono ?: [];
        // Detectar si ya viene en formato multi o aún en legacy
        $primero = $polys[0] ?? null;
        if (is_array($primero) && isset($primero[0]) && !is_array($primero[0])) {
            // Está en legacy [[lat,lng],...] — wrap
            $polys = [$polys];
        }

        $valido = false;
        foreach ($polys as $p) {
            if (is_array($p) && count($p) >= 3) { $valido = true; break; }
        }

        if (!$valido) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Dibuja al menos una zona válida (mínimo 3 puntos).']);
            return;
        }

        $this->sede->update([
            'cobertura_poligono'   => $polys,
            'cobertura_centro_lat' => $this->cobertura_centro_lat,
            'cobertura_centro_lng' => $this->cobertura_centro_lng,
            'cobertura_activa'     => true,
        ]);

        $count = count($polys);
        $msg = $count > 1
            ? "✓ Cobertura guardada con {$count} zonas."
            : "✓ Cobertura de la sede guardada.";
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
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
