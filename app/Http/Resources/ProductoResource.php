<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sedeId = $request->query('sede_id') ? (int) $request->query('sede_id') : null;

        return [
            'id'                => $this->id,
            'codigo'            => $this->codigo,
            'nombre'            => $this->nombre,
            'descripcion'       => $this->descripcion,
            'descripcion_corta' => $this->descripcion_corta,
            'unidad'            => $this->unidad,
            'precio_base'       => (float) $this->precio_base,
            'precio_sede'       => $sedeId ? $this->precioParaSede($sedeId) : null,
            'disponible_sede'   => $sedeId ? $this->disponibleEnSede($sedeId) : null,
            'imagen_url'        => $this->imagen_url,
            'palabras_clave'    => $this->palabras_clave ?? [],
            'activo'            => (bool) $this->activo,
            'destacado'         => (bool) $this->destacado,
            'orden'             => (int) $this->orden,
            'categoria'         => $this->whenLoaded('categoria', fn () => [
                'id'          => $this->categoria->id,
                'nombre'      => $this->categoria->nombre,
                'icono_emoji' => $this->categoria->icono_emoji,
            ]),
            'sedes' => $this->whenLoaded('sedes', fn () =>
                $this->sedes->map(fn ($sede) => [
                    'id'         => $sede->id,
                    'nombre'     => $sede->nombre,
                    'precio'     => $sede->pivot->precio !== null ? (float) $sede->pivot->precio : (float) $this->precio_base,
                    'disponible' => (bool) $sede->pivot->disponible,
                    'nota_sede'  => $sede->pivot->nota_sede,
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
