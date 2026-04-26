<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlujoBot extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'flujos_bot';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'descripcion',
        'activo',
        'prioridad',
        'grafo',
    ];

    protected $casts = [
        'activo'    => 'boolean',
        'prioridad' => 'integer',
        'grafo'     => 'array',
    ];

    /**
     * Devuelve el nodo trigger del flujo (entry point).
     */
    public function nodoTrigger(): ?array
    {
        $nodos = $this->grafo['drawflow']['Home']['data'] ?? [];
        foreach ($nodos as $nodo) {
            if (($nodo['data']['tipo'] ?? null) === 'trigger') {
                return $nodo;
            }
        }
        return null;
    }

    /**
     * Devuelve los nodos siguientes a uno dado, según las conexiones del puerto especificado.
     * Por defecto sale por output_1; las condiciones usan output_1 (true) y output_2 (false).
     */
    public function nodosSiguientes(int $nodoId, string $puerto = 'output_1'): array
    {
        $nodos = $this->grafo['drawflow']['Home']['data'] ?? [];
        $actual = $nodos[$nodoId] ?? null;
        if (!$actual) return [];

        $conexiones = $actual['outputs'][$puerto]['connections'] ?? [];
        $siguientes = [];
        foreach ($conexiones as $c) {
            $idDestino = (int) ($c['node'] ?? 0);
            if ($idDestino && isset($nodos[$idDestino])) {
                $siguientes[] = $nodos[$idDestino];
            }
        }
        return $siguientes;
    }

    public function nodoPorId(int $id): ?array
    {
        return $this->grafo['drawflow']['Home']['data'][$id] ?? null;
    }
}
