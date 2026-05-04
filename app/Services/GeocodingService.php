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
        ?string $departamento = null,
        ?string $pais = 'Colombia'
    ): ?array {
        // 🧠 Si no nos pasaron departamento, INFIERELO según la ciudad para no
        // contaminar la query con "Antioquia" cuando la dirección es de Bogotá.
        // Mapeo de ciudades grandes y de capitales de departamento conocidas.
        if ($departamento === null && $ciudad) {
            $departamento = $this->inferirDepartamento($ciudad);
        }
        // 🌍 PREFERIR Google Geocoding API si el tenant tiene server key
        $resultadoGoogle = $this->geocodificarGoogle($direccion, $barrio, $ciudad, $departamento, $pais);
        if ($resultadoGoogle) return $resultadoGoogle;

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

    /**
     * Geocodifica usando Google Maps Geocoding API si el tenant tiene
     * configurada una server-side API key (sin restricción HTTP referrer).
     * Mucho más preciso que Nominatim para direcciones colombianas.
     *
     * Returns: ['lat'=>float, 'lng'=>float, 'display'=>string] o null.
     */
    private function geocodificarGoogle(
        string $direccion,
        ?string $barrio,
        ?string $ciudad,
        ?string $departamento,
        ?string $pais
    ): ?array {
        $tenant = app(\App\Services\TenantManager::class)->current();
        if (!$tenant || empty($tenant->google_maps_server_api_key)) {
            return null; // No hay server key, fallback a Nominatim
        }

        $apiKey = $tenant->google_maps_server_api_key;
        $address = trim(implode(', ', array_filter([
            $direccion, $barrio, $ciudad, $departamento, $pais,
        ])));

        if ($address === '') return null;

        $cacheKey = 'gmaps_geocode_' . md5($address);

        return Cache::remember($cacheKey, 86400, function () use ($address, $apiKey) {
            try {
                $resp = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $address,
                    'key'     => $apiKey,
                    'region'  => 'co',
                    'language' => 'es',
                ]);

                if (!$resp->successful()) {
                    Log::warning('🗺️ Google Geocoding HTTP error', ['status' => $resp->status()]);
                    return null;
                }

                $body = $resp->json();
                $status = $body['status'] ?? '';

                if ($status !== 'OK' || empty($body['results'][0])) {
                    Log::info('🗺️ Google Geocoding sin resultado', [
                        'address' => $address,
                        'status'  => $status,
                        'error'   => $body['error_message'] ?? null,
                    ]);
                    return null;
                }

                $primero = $body['results'][0];
                $loc = $primero['geometry']['location'];

                Log::info('✅ Google Geocoding resuelto', [
                    'address' => $address,
                    'lat'     => $loc['lat'],
                    'lng'     => $loc['lng'],
                    'display' => $primero['formatted_address'] ?? null,
                    'tipo'    => $primero['geometry']['location_type'] ?? null,
                ]);

                return [
                    'lat'     => (float) $loc['lat'],
                    'lng'     => (float) $loc['lng'],
                    'display' => $primero['formatted_address'] ?? $address,
                    'fuente'  => 'google',
                ];
            } catch (\Throwable $e) {
                Log::warning('🗺️ Google Geocoding excepción: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Infiere el departamento colombiano a partir del nombre de ciudad.
     * Si la ciudad no está en el mapa, devuelve null (mejor sin departamento
     * que con uno equivocado — Google resuelve mejor sin contradicciones).
     */
    private function inferirDepartamento(string $ciudad): ?string
    {
        $c = mb_strtolower(trim($ciudad));
        // Quitar tildes y caracteres especiales para matching robusto
        $c = strtr($c, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

        $mapa = [
            // Bogotá D.C.
            'bogota' => 'Cundinamarca', 'bogota d.c.' => 'Cundinamarca',
            'soacha' => 'Cundinamarca', 'chia' => 'Cundinamarca', 'zipaquira' => 'Cundinamarca',
            'mosquera' => 'Cundinamarca', 'funza' => 'Cundinamarca', 'cajica' => 'Cundinamarca',

            // Antioquia
            'medellin' => 'Antioquia', 'bello' => 'Antioquia', 'envigado' => 'Antioquia',
            'itagui' => 'Antioquia', 'sabaneta' => 'Antioquia', 'la estrella' => 'Antioquia',
            'caldas' => 'Antioquia', 'copacabana' => 'Antioquia', 'girardota' => 'Antioquia',
            'barbosa' => 'Antioquia', 'rionegro' => 'Antioquia', 'concordia' => 'Antioquia',

            // Valle del Cauca
            'cali' => 'Valle del Cauca', 'palmira' => 'Valle del Cauca',
            'buenaventura' => 'Valle del Cauca', 'tulua' => 'Valle del Cauca',
            'jamundi' => 'Valle del Cauca', 'yumbo' => 'Valle del Cauca',

            // Atlántico
            'barranquilla' => 'Atlantico', 'soledad' => 'Atlantico', 'malambo' => 'Atlantico',

            // Bolívar
            'cartagena' => 'Bolivar', 'magangue' => 'Bolivar',

            // Magdalena
            'santa marta' => 'Magdalena', 'cienaga' => 'Magdalena',

            // Cesar
            'valledupar' => 'Cesar',

            // Santander
            'bucaramanga' => 'Santander', 'floridablanca' => 'Santander',
            'giron' => 'Santander', 'piedecuesta' => 'Santander',

            // Norte de Santander
            'cucuta' => 'Norte de Santander',

            // Risaralda
            'pereira' => 'Risaralda', 'dosquebradas' => 'Risaralda',

            // Quindío
            'armenia' => 'Quindio',

            // Caldas
            'manizales' => 'Caldas',

            // Tolima
            'ibague' => 'Tolima',

            // Huila
            'neiva' => 'Huila',

            // Nariño
            'pasto' => 'Narino', 'ipiales' => 'Narino', 'tumaco' => 'Narino',

            // Cauca
            'popayan' => 'Cauca',

            // Boyacá
            'tunja' => 'Boyaca', 'duitama' => 'Boyaca', 'sogamoso' => 'Boyaca',

            // Meta
            'villavicencio' => 'Meta',

            // Córdoba
            'monteria' => 'Cordoba',

            // Sucre
            'sincelejo' => 'Sucre',

            // La Guajira
            'riohacha' => 'La Guajira', 'maicao' => 'La Guajira',

            // Chocó
            'quibdo' => 'Choco',

            // Amazonas
            'leticia' => 'Amazonas',
        ];

        return $mapa[$c] ?? null;
    }
}
