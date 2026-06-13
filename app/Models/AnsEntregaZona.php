<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ⏱️ ANS de entrega en ruta por ZONA. Cada zona define cuánto debería tardar
 * la entrega (mín = objetivo verde, máx = límite rojo).
 */
class AnsEntregaZona extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'ans_entrega_zona';

    protected $fillable = [
        'tenant_id', 'zona_cobertura_id', 'minutos_min', 'minutos_max', 'activo',
    ];

    protected $casts = [
        'minutos_min' => 'integer',
        'minutos_max' => 'integer',
        'activo'      => 'boolean',
    ];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaCobertura::class, 'zona_cobertura_id');
    }
}
