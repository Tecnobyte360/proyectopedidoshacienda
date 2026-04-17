<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZonaBarrio extends Model
{
    protected $table = 'zona_barrios';

    protected $fillable = [
        'zona_cobertura_id',
        'nombre',
        'nombre_normalizado',
    ];

    protected static function booted(): void
    {
        static::saving(function (ZonaBarrio $b) {
            $b->nombre_normalizado = ZonaCobertura::normalizar($b->nombre);
        });
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaCobertura::class, 'zona_cobertura_id');
    }
}
