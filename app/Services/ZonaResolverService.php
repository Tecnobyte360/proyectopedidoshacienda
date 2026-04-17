<?php

namespace App\Services;

use App\Models\ZonaCobertura;
use Illuminate\Support\Collection;

/**
 * Resuelve la zona de cobertura a partir de coordenadas o nombre de barrio.
 * Usa point-in-polygon (algoritmo ray-casting) para coordenadas geográficas.
 */
class ZonaResolverService
{
    /**
     * Encuentra la primera zona activa que contenga el punto (lat, lng).
     */
    public function porCoordenadas(float $lat, float $lng, ?int $sedeId = null): ?ZonaCobertura
    {
        $zonas = ZonaCobertura::query()
            ->where('activa', true)
            ->whereNotNull('poligono')
            ->when($sedeId, fn ($q) => $q->where(function ($qq) use ($sedeId) {
                $qq->where('sede_id', $sedeId)->orWhereNull('sede_id');
            }))
            ->orderBy('orden')
            ->get();

        foreach ($zonas as $zona) {
            if ($this->puntoEnPoligono($lat, $lng, $zona->poligono)) {
                return $zona;
            }
        }

        return null;
    }

    /**
     * Encuentra la zona por nombre de barrio (delegando al modelo).
     */
    public function porBarrio(string $barrio, ?int $sedeId = null): ?ZonaCobertura
    {
        return ZonaCobertura::resolverPorBarrio($barrio, $sedeId);
    }

    /**
     * Resolución combinada: intenta primero por coordenadas, luego por barrio.
     */
    public function resolver(?float $lat, ?float $lng, ?string $barrio = null, ?int $sedeId = null): ?ZonaCobertura
    {
        if ($lat !== null && $lng !== null) {
            $zona = $this->porCoordenadas($lat, $lng, $sedeId);
            if ($zona) return $zona;
        }

        if (!empty($barrio)) {
            return $this->porBarrio($barrio, $sedeId);
        }

        return null;
    }

    /**
     * Verifica si un punto está dentro de un polígono usando ray-casting.
     *
     * @param float  $lat        Latitud del punto
     * @param float  $lng        Longitud del punto
     * @param array  $poligono   Array de vértices [[lat, lng], [lat, lng], ...]
     */
    public function puntoEnPoligono(float $lat, float $lng, array $poligono): bool
    {
        if (count($poligono) < 3) {
            return false;
        }

        $dentro = false;
        $n = count($poligono);
        $j = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $xi = (float) $poligono[$i][1]; // lng
            $yi = (float) $poligono[$i][0]; // lat
            $xj = (float) $poligono[$j][1];
            $yj = (float) $poligono[$j][0];

            $intersecta = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-9) + $xi);

            if ($intersecta) {
                $dentro = !$dentro;
            }

            $j = $i;
        }

        return $dentro;
    }

    /**
     * Devuelve todas las zonas activas que contienen el punto (útil si se solapan).
     */
    public function todasLasZonasContenedoras(float $lat, float $lng): Collection
    {
        return ZonaCobertura::query()
            ->where('activa', true)
            ->whereNotNull('poligono')
            ->get()
            ->filter(fn ($z) => $this->puntoEnPoligono($lat, $lng, $z->poligono))
            ->values();
    }
}
