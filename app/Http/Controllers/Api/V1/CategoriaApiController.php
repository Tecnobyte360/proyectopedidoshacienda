<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\ProductoCategoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductoCategoria::query()->withCount('productos');

        if ($request->boolean('solo_activas')) {
            $query->activas();
        }

        if ($search = $request->query('q')) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        return CategoriaResource::collection(
            $query->orderBy('orden')->orderBy('nombre')->paginate($perPage)
        )->response();
    }

    public function show(int $id): JsonResponse
    {
        $cat = ProductoCategoria::withCount('productos')->findOrFail($id);

        return (new CategoriaResource($cat))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'icono_emoji' => 'nullable|string|max:8',
            'color'       => 'nullable|string|max:16',
            'orden'       => 'integer|min:0',
            'activo'      => 'boolean',
        ]);

        $cat = ProductoCategoria::create($data);

        return (new CategoriaResource($cat))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cat = ProductoCategoria::findOrFail($id);

        $data = $request->validate([
            'nombre'      => 'sometimes|required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'icono_emoji' => 'nullable|string|max:8',
            'color'       => 'nullable|string|max:16',
            'orden'       => 'integer|min:0',
            'activo'      => 'boolean',
        ]);

        $cat->update($data);

        return (new CategoriaResource($cat->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $cat = ProductoCategoria::withCount('productos')->findOrFail($id);

        if ($cat->productos_count > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => "No se puede eliminar: tiene {$cat->productos_count} productos asociados.",
            ], 422);
        }

        $cat->delete();

        return response()->json(['status' => 'ok', 'message' => 'Categoría eliminada.']);
    }
}
