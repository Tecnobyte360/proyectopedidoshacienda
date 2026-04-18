<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocodificación de direcciones usando Nominatim (OpenStreetMap).
 * Libre, sin API key. Cachea resultados por 24h para no saturar el servicio.
 */
class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /** Timeout corto para no bloquear el bot */
    private const TIMEOUT_SEGUNDOS = 8;

    /**
     * Intenta geocodificar una dirección a [lat, lng].
     * Si no logra, devuelve null.
     *
     * @return array{lat: float, lng: float, display: string}|null
     */
    public function geocodificar(
        string $direccion,
        ?string $barrio = null,
        ?string $ciudad = 'Bello',
        ?string $departamento = 'Antioquia',
        ?string $pais = 'Colombia'
    ): ?array {
        $partes = array_filter([$direccion, $barrio, $ciudad, $departamento, $pais]);
        $query  = trim(implode(', ', $partes));

        if ($query === '') {
            return null;
        }

        $cacheKey = 'geocode:' . md5($query);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
            try {
                $response = Http::timeout(self::TIMEOUT_SEGUNDOS)
                    ->withHeaders([
                        // Nominatim exige User-Agent identificable
                        'User-Agent' => 'AlimentosLaHacienda-Bot/1.0 (pedidosonline.tecnobyte360.com)',
                        'Accept-Language' => 'es',
                    ])
                    ->get(self::ENDPOINT, [
                        'q'              => $query,
                        'format'         => 'json',
                        'limit'          => 1,
                        'countrycodes'   => 'co',
                        'addressdetails' => 0,
                    ]);

                if (!$response->successful()) {
                    Log::warning('Geocoding: respuesta no OK', [
                        'status' => $response->status(),
                        'query'  => $query,
                    ]);
                    return null;
                }

                $data = $response->json();
                if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
                    return null;
                }

                return [
                    'lat'     => (float) $data[0]['lat'],
                    'lng'     => (float) $data[0]['lon'],
                    'display' => (string) ($data[0]['display_name'] ?? $query),
                ];
            } catch (\Throwable $e) {
                Log::warning('Geocoding: excepción', [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
                return null;
            }
        });
    }
}
