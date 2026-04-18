<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'latitud',
        'longitud',
        'hora_apertura',
        'hora_cierre',
        'activa',
    ];

    protected $casts = [
        'latitud'  => 'float',
        'longitud' => 'float',
        'activa'   => 'boolean',
    ];

    /**
     * Devuelve la sede más cercana a un punto (lat, lng).
     * Usa fórmula haversine.
     * Si ninguna sede tiene coordenadas, devuelve la primera activa.
     */
    public static function masCercanaA(float $lat, float $lng): ?self
    {
        $sedes = self::where('activa', true)->get();

        if ($sedes->isEmpty()) {
            return null;
        }

        $conCoords = $sedes->filter(fn ($s) => $s->latitud && $s->longitud);

        // Si ninguna sede tiene coordenadas, fallback a la primera activa
        if ($conCoords->isEmpty()) {
            return $sedes->first();
        }

        return $conCoords
            ->map(function ($s) use ($lat, $lng) {
                $s->_distancia_km = self::distanciaKm($lat, $lng, $s->latitud, $s->longitud);
                return $s;
            })
            ->sortBy('_distancia_km')
            ->first();
    }

    /**
     * Haversine — distancia en km entre dos puntos.
     */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radioTierra * $c;
    }
}
