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
     *
     * Estrategia: prueba varias variantes de la query porque Nominatim es
     * flojo con direcciones colombianas (no entiende "#", "Apto", "bb", etc).
     * Se queda con el primer match que devuelva algo.
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
        $direccionLimpia = $this->limpiarDireccion($direccion);

        // Generar varias versiones de query (de más específica a más general)
        $queries = array_filter(array_unique([
            // 1. Dirección limpia + barrio + ciudad
            trim(implode(', ', array_filter([$direccionLimpia, $barrio, $ciudad, $departamento, $pais]))),

            // 2. Solo barrio + ciudad (muchas veces es lo único que resuelve bien)
            $barrio ? trim(implode(', ', array_filter([$barrio, $ciudad, $departamento, $pais]))) : null,

            // 3. Dirección original sin limpiar (por si acaso)
            trim(implode(', ', array_filter([$direccion, $barrio, $ciudad, $departamento, $pais]))),

            // 4. Dirección + ciudad (sin barrio, por si el barrio confunde)
            $direccionLimpia ? trim(implode(', ', array_filter([$direccionLimpia, $ciudad, $departamento, $pais]))) : null,
        ]));

        if (empty($queries)) {
            return null;
        }

        foreach ($queries as $query) {
            $resultado = $this->geocodeQuery($query);
            if ($resultado) {
                Log::info('🗺️ Geocoding resuelto', [
                    'query'    => $query,
                    'resultado' => $resultado['display'],
                ]);
                return $resultado;
            }
        }

        Log::info('🗺️ Geocoding sin resultado', [
            'direccion' => $direccion,
            'barrio'    => $barrio,
            'variantes_probadas' => count($queries),
        ]);

        return null;
    }

    /**
     * Convierte formato colombiano a algo que Nominatim entienda mejor.
     *   "Calle 41 #59bb 35"  →  "Calle 41 59 35"
     *   "Cra 50 # 45-12"     →  "Carrera 50 45 12"
     *   "Apto 1214"          →  (se quita)
     */
    private function limpiarDireccion(string $direccion): string
    {
        $d = $direccion;

        // Normalizar abreviaturas
        $d = preg_replace('/\bCra\.?\b/i', 'Carrera', $d);
        $d = preg_replace('/\bCll\.?\b/i', 'Calle', $d);
        $d = preg_replace('/\bKra\.?\b/i', 'Carrera', $d);
        $d = preg_replace('/\bAv\.?\b/i', 'Avenida', $d);
        $d = preg_replace('/\bDg\.?\b/i', 'Diagonal', $d);
        $d = preg_replace('/\bTv\.?\b/i', 'Transversal', $d);

        // Quitar partes que confunden a Nominatim (piso, apto, torre, etc)
        $d = preg_replace('/\b(apto|apartamento|apt|torre|bloque|interior|int|piso|casa|oficina|local)\s*[\wáéíóúñ\d-]+/iu', '', $d);

        // Convertir # y - entre números en espacios (más amigable)
        $d = preg_replace('/#\s*/', ' ', $d);
        $d = preg_replace('/(\d)\s*-\s*(\d)/', '$1 $2', $d);

        // Quitar letras pegadas a números (59bb → 59)
        $d = preg_replace('/(\d+)[a-zA-Z]+/', '$1', $d);

        // Colapsar espacios
        $d = preg_replace('/\s+/', ' ', $d);

        return trim($d);
    }

    /**
     * Llama Nominatim con una query específica y cachea el resultado.
     */
    private function geocodeQuery(string $query): ?array
    {
        if ($query === '') return null;

        $cacheKey = 'geocode:' . md5($query);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
            try {
                $response = Http::timeout(self::TIMEOUT_SEGUNDOS)
                    ->withHeaders([
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
