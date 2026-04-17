<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromocionResource;
use App\Models\Promocion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromocionApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Promocion::query()->with(['productos', 'sedes']);

        if ($request->boolean('solo_vigentes')) {
            $query->vigentes();
        }

        if ($cupon = $request->query('cupon')) {
            $query->where('codigo_cupon', $cupon);
        }

        if ($search = $request->query('q')) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->query('per_page', 30), 200);

        return PromocionResource::collection(
            $query->orderBy('orden')->orderByDesc('id')->paginate($perPage)
        )->response();
    }

    public function show(int $id): JsonResponse
    {
        $promo = Promocion::with(['productos', 'sedes'])->findOrFail($id);

        return (new PromocionResource($promo))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $promo = Promocion::create($data);

        if ($request->has('productos')) {
            $promo->productos()->sync($request->input('productos', []));
        }
        if ($request->has('sedes')) {
            $promo->sedes()->sync($request->input('sedes', []));
        }

        return (new PromocionResource($promo->load(['productos', 'sedes'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $promo = Promocion::findOrFail($id);

        $data = $this->validateData($request, true);

        $promo->update($data);

        if ($request->has('productos')) {
            $promo->productos()->sync($request->input('productos', []));
        }
        if ($request->has('sedes')) {
            $promo->sedes()->sync($request->input('sedes', []));
        }

        return (new PromocionResource($promo->fresh(['productos', 'sedes'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        Promocion::findOrFail($id)->delete();

        return response()->json(['status' => 'ok', 'message' => 'Promoción eliminada.']);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'nombre'                 => "{$required}|string|max:160",
            'descripcion'            => 'nullable|string|max:255',
            'tipo'                   => "{$required}|in:porcentaje,monto_fijo,precio_especial,nx1",
            'valor'                  => "{$required}|numeric|min:0",
            'compra'                 => 'nullable|integer|min:1',
            'paga'                   => 'nullable|integer|min:1',
            'fecha_inicio'           => 'nullable|date',
            'fecha_fin'              => 'nullable|date|after_or_equal:fecha_inicio',
            'imagen_url'             => 'nullable|url|max:500',
            'codigo_cupon'           => 'nullable|string|max:60',
            'activa'                 => 'boolean',
            'aplica_todos_productos' => 'boolean',
            'aplica_todas_sedes'     => 'boolean',
            'orden'                  => 'integer|min:0',
            'productos'              => 'nullable|array',
            'productos.*'            => 'integer|exists:productos,id',
            'sedes'                  => 'nullable|array',
            'sedes.*'                => 'integer|exists:sedes,id',
        ]);
    }
}
