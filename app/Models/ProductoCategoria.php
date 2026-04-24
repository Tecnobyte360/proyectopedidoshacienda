<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoCategoria extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'productos_categorias';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'descripcion',
        'icono_emoji',
        'color',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
