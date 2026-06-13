<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 👥 Grupo de clientes — lista dinámica para difundir mensajes (plantilla Meta)
 * a varios clientes a la vez. Cada cliente igual recibe el mensaje en su chat
 * privado (la API de Meta no soporta grupos reales).
 */
class GrupoCliente extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'grupos_clientes';

    protected $fillable = [
        'tenant_id', 'nombre', 'descripcion', 'color',
    ];

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_grupo', 'grupo_id', 'cliente_id')
            ->withTimestamps();
    }
}
