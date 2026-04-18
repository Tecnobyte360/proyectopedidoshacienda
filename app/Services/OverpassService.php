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
     * @param  array  $poligono  Array de vértices [[lat, lng], [lat, lng], ...]
     * @return array<int,string> Nombres únicos ordenados.
     */
    public function barriosEnPoligono(array $poligono): array
    {
        if (count($poligono) < 3) {
            return [];
        }

        // Overpass espera: "lat1 lng1 lat2 lng2 lat3 lng3 ..."
        $poly = collect($poligono)
            ->map(fn ($p) => ((float) $p[0]) . ' ' . ((float) $p[1]))
            ->implode(' ');

        $cacheKey = 'overpass_barrios:' . md5($poly);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($poly) {
            // Pedimos nodos con place=suburb|neighbourhood|quarter|village|hamlet
            // más relaciones administrativas con boundary=administrative
            $query = <<<OQL
[out:json][timeout:20];
(
  node["place"~"suburb|neighbourhood|quarter|village|hamlet|locality"](poly:"{$poly}");
  way["place"~"suburb|neighbourhood|quarter|village|hamlet|locality"](poly:"{$poly}");
  relation["place"~"suburb|neighbourhood|quarter|village|hamlet|locality"](poly:"{$poly}");
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

                    if (!$response->successful()) {
                        Log::info('Overpass: respuesta no OK, probando siguiente', [
                            'endpoint' => $endpoint,
                            'status'   => $response->status(),
                        ]);
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

                    // Deduplicar (case-insensitive) y ordenar alfabéticamente
                    $unicos = collect($nombres)
                        ->unique(fn ($n) => mb_strtolower($n))
                        ->sort()
                        ->values()
                        ->all();

                    Log::info('🗺️ Overpass OK', [
                        'endpoint' => $endpoint,
                        'barrios_detectados' => count($unicos),
                    ]);

                    return $unicos;
                } catch (\Throwable $e) {
                    Log::info('Overpass: excepción, probando siguiente', [
                        'endpoint' => $endpoint,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            Log::warning('Overpass: todos los endpoints fallaron');
            return [];
        });
    }
}
