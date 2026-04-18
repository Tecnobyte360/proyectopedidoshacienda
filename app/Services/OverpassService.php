<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta la Overpass API (OpenStreetMap) para obtener
 * barrios / vecindarios / sectores dentro de un polígono.
 *
 * Uso típico: el admin dibuja una zona en el mapa y este servicio
 * auto-detecta los barrios que caen dentro.
 */
class OverpassService
{
    /** Endpoints públicos de Overpass (se rotan por si uno está caído) */
    private const ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
    ];

    private const TIMEOUT_SEGUNDOS = 25;

    /**
     * Devuelve los nombres de barrios/sectores dentro del polígono.
     *
     * Estrategia dual:
     *   1. Overpass API (rápida, lista todos los place=suburb del polígono)
     *   2. Si Overpass devuelve vacío → fallback con Nominatim reverse geocoding
     *      sobre una malla de puntos dentro del polígono.
     *
     * @param  array  $poligono  Array de vértices [[lat, lng], [lat, lng], ...]
     * @return array<int,string> Nombres únicos ordenados.
     */
    public function barriosEnPoligono(array $poligono): array
    {
        if (count($poligono) < 3) {
            return [];
        }

        $cacheKey = 'overpass_barrios:' . md5(json_encode($poligono));

        // NO uses Cache::remember porque cachea resultados vacíos
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $barrios = $this->consultarOverpass($poligono);

        // Fallback si Overpass no dio nada
        if (empty($barrios)) {
            Log::info('🗺️ Overpass vacío, usando fallback Nominatim-reverse');
            $barrios = $this->consultarNominatimReverse($poligono);
        }

        if (!empty($barrios)) {
            Cache::put($cacheKey, $barrios, now()->addHours(6));
        }

        return $barrios;
    }

    /**
     * Query directa a Overpass.
     */
    private function consultarOverpass(array $poligono): array
    {
        $poly = collect($poligono)
            ->map(fn ($p) => ((float) $p[0]) . ' ' . ((float) $p[1]))
            ->implode(' ');

        $query = <<<OQL
[out:json][timeout:20];
(
  node["place"~"suburb|neighbourhood|quarter|village|hamlet|locality|town"](poly:"{$poly}");
  way["place"~"suburb|neighbourhood|quarter|village|hamlet|locality|town"](poly:"{$poly}");
  relation["place"~"suburb|neighbourhood|quarter|village|hamlet|locality|town"](poly:"{$poly}");
  relation["boundary"="administrative"]["admin_level"~"^(8|9|10)$"](poly:"{$poly}");
);
out tags center;
OQL;

        foreach (self::ENDPOINTS as $endpoint) {
            try {
                $response = Http::timeout(self::TIMEOUT_SEGUNDOS)
                    ->withHeaders([
                        'User-Agent' => 'AlimentosLaHacienda-Admin/1.0',
                    ])
                    ->asForm()
                    ->post($endpoint, ['data' => $query]);

                Log::info('Overpass: llamada', [
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                    'body_len' => strlen($response->body()),
                ]);

                if (!$response->successful()) {
                    continue;
                }

                $data = $response->json('elements', []);
                $nombres = [];

                foreach ($data as $el) {
                    $tags = $el['tags'] ?? [];
                    $nombre = $tags['name:es']
                           ?? $tags['name']
                           ?? $tags['alt_name']
                           ?? null;

                    if ($nombre) {
                        $nombres[] = trim((string) $nombre);
                    }
                }

                $unicos = collect($nombres)
                    ->unique(fn ($n) => mb_strtolower($n))
                    ->sort()
                    ->values()
                    ->all();

                Log::info('Overpass: resultado', [
                    'endpoint' => $endpoint,
                    'elements' => count($data),
                    'nombres'  => count($unicos),
                ]);

                return $unicos;
            } catch (\Throwable $e) {
                Log::info('Overpass: excepción', [
                    'endpoint' => $endpoint,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * Fallback: malla de puntos dentro del polígono + Nominatim reverse.
     * Funciona siempre (Nominatim es muy estable), pero es más lento.
     */
    private function consultarNominatimReverse(array $poligono): array
    {
        // Calcular bounding box
        $lats = array_column($poligono, 0);
        $lngs = array_column($poligono, 1);
        $minLat = min($lats); $maxLat = max($lats);
        $minLng = min($lngs); $maxLng = max($lngs);

        // Malla 6x6 = 36 puntos candidatos
        $pasos = 6;
        $puntos = [];
        for ($i = 0; $i < $pasos; $i++) {
            for ($j = 0; $j < $pasos; $j++) {
                $lat = $minLat + ($maxLat - $minLat) * ($i + 0.5) / $pasos;
                $lng = $minLng + ($maxLng - $minLng) * ($j + 0.5) / $pasos;
                if ($this->puntoEnPoligono($lat, $lng, $poligono)) {
                    $puntos[] = [$lat, $lng];
                }
            }
        }

        Log::info('Nominatim-reverse: puntos de muestreo', ['puntos' => count($puntos)]);

        $nombres = [];
        foreach ($puntos as $p) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent'      => 'AlimentosLaHacienda-Admin/1.0',
                        'Accept-Language' => 'es',
                    ])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat'    => $p[0],
                        'lon'    => $p[1],
                        'format' => 'json',
                        'zoom'   => 17,
                        'addressdetails' => 1,
                    ]);

                if (!$response->successful()) continue;

                $addr = $response->json('address', []);
                foreach (['suburb', 'neighbourhood', 'quarter', 'hamlet', 'village', 'residential', 'city_district'] as $k) {
                    if (!empty($addr[$k])) {
                        $nombres[] = trim((string) $addr[$k]);
                    }
                }

                // Nominatim exige 1 req/segundo
                usleep(1_100_000);
            } catch (\Throwable $e) {
                // seguir con el siguiente punto
            }
        }

        $unicos = collect($nombres)
            ->unique(fn ($n) => mb_strtolower($n))
            ->sort()
            ->values()
            ->all();

        Log::info('Nominatim-reverse: resultado', [
            'barrios_unicos' => count($unicos),
        ]);

        return $unicos;
    }

    /**
     * Ray-casting para point-in-polygon (copiado de ZonaResolverService
     * para que este servicio sea autónomo).
     */
    private function puntoEnPoligono(float $lat, float $lng, array $poligono): bool
    {
        if (count($poligono) < 3) return false;

        $dentro = false;
        $n = count($poligono);
        $j = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $xi = (float) $poligono[$i][1];
            $yi = (float) $poligono[$i][0];
            $xj = (float) $poligono[$j][1];
            $yj = (float) $poligono[$j][0];

            $intersecta = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-9) + $xi);

            if ($intersecta) $dentro = !$dentro;
            $j = $i;
        }

        return $dentro;
    }
}
