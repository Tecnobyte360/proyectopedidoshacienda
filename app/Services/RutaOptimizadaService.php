<?php

namespace App\Services;

use App\Models\Pedido;
use Illuminate\Support\Collection;

/**
 * Optimiza el orden de entrega de pedidos para un domiciliario.
 *
 * Usa el algoritmo "vecino más cercano" (nearest neighbor) — heurística simple
 * y rápida que ordena los pedidos minimizando la distancia total a recorrer.
 * No es el óptimo absoluto (TSP es NP-hard) pero da resultados muy buenos
 * para 5-15 paradas que es lo típico de un domiciliario.
 *
 * Genera además la URL de Google Maps con los waypoints en el orden óptimo,
 * lista para abrir en el celular del domiciliario.
 */
class RutaOptimizadaService
{
    /**
     * Ordena los pedidos por proximidad partiendo del punto de origen.
     *
     * @param Collection $pedidos Pedidos con lat/lng llenos.
     * @param float|null $origenLat Lat de partida (típicamente la sede o la ubicación actual del domi).
     * @param float|null $origenLng Lng de partida.
     * @return Collection Pedidos en orden óptimo de visita.
     */
    public function optimizar(Collection $pedidos, ?float $origenLat = null, ?float $origenLng = null): Collection
    {
        $conCoords = $pedidos->filter(fn ($p) => $p->lat && $p->lng)->values();
        $sinCoords = $pedidos->filter(fn ($p) => !$p->lat || !$p->lng)->values();

        if ($conCoords->isEmpty()) {
            return $pedidos; // sin coords = no podemos optimizar, devolvemos como vino
        }

        // Si no hay origen, usar el primer pedido como punto de partida
        $lat = $origenLat;
        $lng = $origenLng;
        if ($lat === null || $lng === null) {
            $primer = $conCoords->shift();
            $ordenados = collect([$primer]);
            $lat = (float) $primer->lat;
            $lng = (float) $primer->lng;
        } else {
            $ordenados = collect();
        }

        $restantes = $conCoords;

        while ($restantes->isNotEmpty()) {
            // Buscar el más cercano al punto actual
            $cercano = $restantes->sortBy(fn ($p) => $this->haversine($lat, $lng, (float) $p->lat, (float) $p->lng))->first();
            $ordenados->push($cercano);
            $lat = (float) $cercano->lat;
            $lng = (float) $cercano->lng;
            $restantes = $restantes->reject(fn ($p) => $p->id === $cercano->id)->values();
        }

        // Pedidos sin coordenadas van al final
        return $ordenados->concat($sinCoords);
    }

    /**
     * Genera URL de Google Maps con la ruta optimizada multi-parada.
     *
     * Formato: https://www.google.com/maps/dir/?api=1&origin=lat,lng&destination=lat,lng&waypoints=lat,lng|lat,lng&travelmode=driving
     */
    public function urlGoogleMaps(Collection $pedidosOrdenados, ?float $origenLat = null, ?float $origenLng = null): ?string
    {
        $puntos = $pedidosOrdenados->filter(fn ($p) => $p->lat && $p->lng)->values();
        if ($puntos->isEmpty()) return null;

        $params = ['api' => '1', 'travelmode' => 'driving'];

        if ($origenLat !== null && $origenLng !== null) {
            $params['origin'] = "{$origenLat},{$origenLng}";
            // Todos los pedidos son waypoints + el último es destino
            $destino = $puntos->pop();
            if ($puntos->isNotEmpty()) {
                $params['waypoints'] = $puntos->map(fn ($p) => "{$p->lat},{$p->lng}")->implode('|');
            }
            $params['destination'] = "{$destino->lat},{$destino->lng}";
        } else {
            // Sin origen: el primer pedido es origen, último es destino, intermedios son waypoints
            $origen  = $puntos->shift();
            $destino = $puntos->pop();
            $params['origin']      = "{$origen->lat},{$origen->lng}";
            $params['destination'] = $destino ? "{$destino->lat},{$destino->lng}" : "{$origen->lat},{$origen->lng}";
            if ($puntos->isNotEmpty()) {
                $params['waypoints'] = $puntos->map(fn ($p) => "{$p->lat},{$p->lng}")->implode('|');
            }
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($params);
    }

    /**
     * Distancia en km entre dos puntos (haversine).
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
