<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Respuestas pre-armadas para el operador en /chat. Se muestran como chips
 * arriba del input para que con un click el texto se pegue al campo.
 */
class RespuestaRapida extends Model
{
    use BelongsToTenant;

    protected $table = 'respuestas_rapidas';

    protected $fillable = ['tenant_id', 'atajo', 'texto', 'orden', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];
}
