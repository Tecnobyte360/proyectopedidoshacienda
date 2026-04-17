<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ZonaCobertura;
use App\Services\ZonaResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZonaApiController extends Controller
{
    public function __construct(private ZonaResolverService $resolver) {}

    public function index(Request $request): JsonResponse
    {
        $query = ZonaCobertura::query()
            ->with('barrios')
            ->withCount(['barrios', 'pedidos']);

        if ($request->boolean('solo_activas')) {
            $query->where('activa', true);
        }

        if ($request->boolean('con_poligono')) {
            $query->whereNotNull('poligono');
        }

        if ($sedeId = $request->query('sede_id')) {
            $query->where('sede_id', (int) $sedeId);
        }

        $zonas = $query->orderBy('orden')->orderBy('nombre')->get()->map(fn ($z) => [
            'id'                  => $z->id,
            'nombre'              => $z->nombre,
            'descripcion'         => $z->descripcion,
            'color'               => $z->color,
            'sede_id'             => $z->sede_id,
            'costo_envio'         => (float) $z->costo_envio,
            'tiempo_estimado_min' => $z->tiempo_estimado_min,
            'centro_lat'          => $z->centro_lat,
            'centro_lng'          => $z->centro_lng,
            'area_km2'            => $z->area_km2,
            'poligono'            => $z->poligono,
            'barrios'             => $z->barrios->pluck('nombre'),
            'barrios_count'       => $z->barrios_count,
            'pedidos_count'       => $z->pedidos_count,
            'activa'              => (bool) $z->activa,
        ]);

        return response()->json(['data' => $zonas]);
    }

    /**
     * Resuelve la zona de cobertura que contiene unas coordenadas o un barrio.
     *
     * Body:
     *   - lat (float, opcional)
     *   - lng (float, opcional)
     *   - barrio (string, opcional)
     *   - sede_id (int, opcional)
     *
     * Al menos uno de (lat+lng) o barrio debe venir.
     */
    public function resolver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'     => 'nullable|numeric|between:-90,90',
            'lng'     => 'nullable|numeric|between:-180,180',
            'barrio'  => 'nullable|string|max:120',
            'sede_id' => 'nullable|integer|exists:sedes,id',
        ]);

        if (empty($data['lat']) && empty($data['barrio'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Debes enviar (lat + lng) o barrio.',
            ], 422);
        }

        $zona = $this->resolver->resolver(
            $data['lat'] ?? null,
            $data['lng'] ?? null,
            $data['barrio'] ?? null,
            $data['sede_id'] ?? null
        );

        if (!$zona) {
            return response()->json([
                'status'   => 'sin_cobertura',
                'message'  => 'No se encontró zona de cobertura para los datos enviados.',
                'criterio' => $data,
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'zona'   => [
                'id'                  => $zona->id,
                'nombre'              => $zona->nombre,
                'color'               => $zona->color,
                'costo_envio'         => (float) $zona->costo_envio,
                'tiempo_estimado_min' => $zona->tiempo_estimado_min,
                'sede_id'             => $zona->sede_id,
                'sede'                => $zona->sede?->nombre,
            ],
        ]);
    }
}
