<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cita / sesión agendada de un tenant.
 */
class Cita extends Model
{
    use BelongsToTenant;

    protected $table = 'citas';
    protected $guarded = ['id'];

    protected $casts = [
        'inicio_at' => 'datetime',
        'fin_at'    => 'datetime',
    ];

    public const ESTADOS = ['pendiente', 'confirmada', 'cancelada', 'completada'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Citas que "ocupan" espacio (todas menos las canceladas). */
    public function scopeActivas($q)
    {
        return $q->where('estado', '!=', 'cancelada');
    }
}
