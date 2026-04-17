<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()->with(['categoria', 'sedes']);

        if ($request->boolean('solo_activos')) {
            $query->activos();
        }

        if ($request->boolean('solo_destacados')) {
            $query->destacados();
        }

        if ($categoriaId = $request->query('categoria_id')) {
            $query->where('categoria_id', (int) $categoriaId);
        }

        if ($sedeId = $request->query('sede_id')) {
            $query->disponibleEnSede((int) $sedeId);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%")
                  ->orWhere('descripcion_corta', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 30), 200);

        return ProductoResource::collection(
            $query->orderBy('orden')->orderBy('nombre')->paginate($perPage)
        )->response();
    }

    public function show(int $id): JsonResponse
    {
        $producto = Producto::with(['categoria', 'sedes'])->findOrFail($id);

        return (new ProductoResource($producto))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $producto = Producto::create($data);

        if ($request->filled('sedes')) {
            $producto->sedes()->sync($this->mapearSedes($request->input('sedes', [])));
        }

        return (new ProductoResource($producto->load(['categoria', 'sedes'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $producto = Producto::findOrFail($id);

        $data = $this->validateData($request, true);

        $producto->update($data);

        if ($request->has('sedes')) {
            $producto->sedes()->sync($this->mapearSedes($request->input('sedes', [])));
        }

        return (new ProductoResource($producto->fresh(['categoria', 'sedes'])))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        Producto::findOrFail($id)->delete();

        return response()->json(['status' => 'ok', 'message' => 'Producto eliminado.']);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'categoria_id'      => 'nullable|exists:productos_categorias,id',
            'codigo'            => 'nullable|string|max:60',
            'nombre'            => "{$required}|string|max:160",
            'descripcion'       => 'nullable|string',
            'descripcion_corta' => 'nullable|string|max:255',
            'unidad'            => "{$required}|string|max:32",
            'precio_base'       => "{$required}|numeric|min:0",
            'imagen_url'        => 'nullable|url|max:500',
            'palabras_clave'    => 'nullable|array',
            'palabras_clave.*'  => 'string|max:60',
            'activo'            => 'boolean',
            'destacado'         => 'boolean',
            'orden'             => 'integer|min:0',
        ]);
    }

    /**
     * Acepta dos formatos:
     * - [{"sede_id":1,"precio":15000,"disponible":true}, ...]
     * - [1, 2, 3]  (sin precio personalizado, todas disponibles)
     */
    private function mapearSedes(array $sedes): array
    {
        $sync = [];

        foreach ($sedes as $sede) {
            if (is_array($sede)) {
                $id = (int) ($sede['sede_id'] ?? 0);
                if ($id > 0) {
                    $sync[$id] = [
                        'precio'     => isset($sede['precio']) ? (float) $sede['precio'] : null,
                        'disponible' => (bool) ($sede['disponible'] ?? true),
                        'nota_sede'  => $sede['nota_sede'] ?? null,
                    ];
                }
            } elseif (is_numeric($sede)) {
                $sync[(int) $sede] = [
                    'precio'     => null,
                    'disponible' => true,
                    'nota_sede'  => null,
                ];
            }
        }

        return $sync;
    }
}
