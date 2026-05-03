<?php

namespace App\Services;

use App\Models\Sede;

/**
 * Resuelve QUÉ SEDE atiende a un cliente según sus coordenadas.
 *
 * Inteligencia integrada:
 *   1. Point-in-polygon multi-sede: si el punto cae en VARIAS sedes,
 *      elige la más cercana (haversine).
 *   2. Si la sede ganadora está CERRADA AHORA, busca la siguiente más
 *      cercana que esté abierta.
 *   3. Si no cae en ninguna sede, ofrece la sede física más cercana
 *      para "recoger en sede" (sin domicilio).
 */
class SedeResolverService
{
    /**
     * Encuentra la mejor sede para atender un punto. Devuelve estructura:
     *
     * [
     *   'sede'                => Sede|null  ← la elegida
     *   'cubierta'            => bool       ← cae dentro del polígono
     *   'distancia_km'        => float      ← desde la sede al punto
     *   'sedes_candidatas'    => Collection ← todas las que cubren el punto
     *   'sede_alternativa'    => Sede|null  ← si la ganadora está cerrada
     *   'recoger_en_sede'     => Sede|null  ← si NO hay cobertura, la más cercana
     *   'metodo'              => string     ← poligono | proximidad_recoger | sin_resultado
     * ]
     */
    public function resolverParaPunto(float $lat, float $lng, ?int $tenantId = null): array
    {
        $sedesActivas = $this->cargarSedesActivas($tenantId);

        if ($sedesActivas->isEmpty()) {
            return [
                'sede' => null, 'cubierta' => false,
                'distancia_km' => null, 'sedes_candidatas' => collect(),
                'sede_alternativa' => null, 'recoger_en_sede' => null,
                'metodo' => 'sin_resultado',
            ];
        }

        // Sedes que cubren el punto vía point-in-polygon
        $candidatas = $sedesActivas
            ->filter(fn ($s) => $s->cobertura_activa && $s->tieneCobertura())
            ->filter(fn ($s) => $this->puntoEnPoligono($lat, $lng, $s->cobertura_poligono))
            ->map(function ($s) use ($lat, $lng) {
                $s->_distancia_punto_km = $s->distanciaA($lat, $lng);
                return $s;
            })
            ->sortBy('_distancia_punto_km')
            ->values();

        // Caso 1: sí cae en al menos una sede
        if ($candidatas->isNotEmpty()) {
            $ganadora = $candidatas->first();
            $alternativa = null;

            // Si la ganadora está cerrada, buscar la siguiente abierta
            if (!$ganadora->estaAbierta()) {
                $alternativa = $candidatas->skip(1)->first(fn ($s) => $s->estaAbierta());
            }

            return [
                'sede'             => $alternativa ?: $ganadora,
                'cubierta'         => true,
                'distancia_km'     => round($ganadora->_distancia_punto_km, 2),
                'sedes_candidatas' => $candidatas,
                'sede_alternativa' => $alternativa,
                'recoger_en_sede'  => null,
                'metodo'           => 'poligono',
            ];
        }

        // Caso 2: ninguna sede cubre el punto → sugerir recoger en la más cercana
        $masCercana = $sedesActivas
            ->map(function ($s) use ($lat, $lng) {
                $s->_distancia_punto_km = $s->distanciaA($lat, $lng);
                return $s;
            })
            ->sortBy('_distancia_punto_km')
            ->first();

        return [
            'sede'             => null,
            'cubierta'         => false,
            'distancia_km'     => $masCercana ? round($masCercana->_distancia_punto_km, 2) : null,
            'sedes_candidatas' => collect(),
            'sede_alternativa' => null,
            'recoger_en_sede'  => $masCercana,
            'metodo'           => 'proximidad_recoger',
        ];
    }

    /**
     * Carga las sedes activas del tenant actual (o de uno específico).
     */
    private function cargarSedesActivas(?int $tenantId = null)
    {
        $q = Sede::where('activa', true);
        if ($tenantId) $q->where('tenant_id', $tenantId);
        return $q->get();
    }

    /**
     * Algoritmo ray-casting para determinar si un punto está dentro
     * de un polígono. Polígono = array de [lat, lng] vértices.
     */
    public function puntoEnPoligono(float $lat, float $lng, array $poligono): bool
    {
        if (count($poligono) < 3) return false;

        $dentro = false;
        $n = count($poligono);
        $j = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $latI = (float) ($poligono[$i][0] ?? 0);
            $lngI = (float) ($poligono[$i][1] ?? 0);
            $latJ = (float) ($poligono[$j][0] ?? 0);
            $lngJ = (float) ($poligono[$j][1] ?? 0);

            $intersect = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-10) + $latI);

            if ($intersect) $dentro = !$dentro;
            $j = $i;
        }

        return $dentro;
    }
}
