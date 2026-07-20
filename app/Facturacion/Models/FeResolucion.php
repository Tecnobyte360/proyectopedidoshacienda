<?php

namespace App\Facturacion\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resolución de numeración DIAN por tenant. `clave_tecnica` cifrada (CUFE).
 */
class FeResolucion extends Model
{
    protected $table = 'fe_resoluciones';
    protected $guarded = ['id'];

    protected $hidden = ['clave_tecnica'];

    protected $casts = [
        'clave_tecnica' => 'encrypted',
        'fecha_desde'   => 'date',
        'fecha_hasta'   => 'date',
        'activa'        => 'boolean',
        'numero_desde'  => 'integer',
        'numero_hasta'  => 'integer',
        'numero_actual' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tieneCupo(): bool
    {
        return $this->numero_actual < $this->numero_hasta;
    }

    public function vigente(?\DateTimeInterface $en = null): bool
    {
        $en = $en ?: now();
        if ($this->fecha_desde && $en < $this->fecha_desde) return false;
        if ($this->fecha_hasta && $en > $this->fecha_hasta) return false;
        return (bool) $this->activa;
    }
}
