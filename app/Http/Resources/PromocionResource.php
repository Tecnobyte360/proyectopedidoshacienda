<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromocionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'nombre'                 => $this->nombre,
            'descripcion'            => $this->descripcion,
            'descripcion_corta'      => $this->descripcionCorta(),
            'tipo'                   => $this->tipo,
            'valor'                  => (float) $this->valor,
            'compra'                 => $this->compra,
            'paga'                   => $this->paga,
            'fecha_inicio'           => $this->fecha_inicio?->toIso8601String(),
            'fecha_fin'              => $this->fecha_fin?->toIso8601String(),
            'imagen_url'             => $this->imagen_url,
            'codigo_cupon'           => $this->codigo_cupon,
            'activa'                 => (bool) $this->activa,
            'vigente'                => $this->estaVigente(),
            'aplica_todos_productos' => (bool) $this->aplica_todos_productos,
            'aplica_todas_sedes'     => (bool) $this->aplica_todas_sedes,
            'productos' => $this->whenLoaded('productos', fn () =>
                $this->productos->map(fn ($p) => [
                    'id' => $p->id, 'nombre' => $p->nombre, 'codigo' => $p->codigo,
                ])
            ),
            'sedes' => $this->whenLoaded('sedes', fn () =>
                $this->sedes->map(fn ($s) => [
                    'id' => $s->id, 'nombre' => $s->nombre,
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
