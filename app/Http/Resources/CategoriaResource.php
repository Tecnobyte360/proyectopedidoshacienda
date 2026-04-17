<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre,
            'descripcion'     => $this->descripcion,
            'icono_emoji'     => $this->icono_emoji,
            'color'           => $this->color,
            'orden'           => (int) $this->orden,
            'activo'          => (bool) $this->activo,
            'productos_count' => $this->whenCounted('productos'),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
