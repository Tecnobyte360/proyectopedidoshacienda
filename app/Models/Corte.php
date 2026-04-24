<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Corte extends Model
{
    use BelongsToTenant;

    protected $table = 'cortes';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'descripcion',
        'icono_emoji',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_corte')
            ->withPivot('orden')
            ->withTimestamps();
    }
}
