<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor respondido para un campo. `valor` es escalar o JSON (checkbox multi).
 */
class EncuestaRespuestaValor extends Model
{
    protected $table = 'encuesta_respuesta_valores';
    protected $guarded = ['id'];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(EncuestaRespuesta::class, 'encuesta_respuesta_id');
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(EncuestaCampo::class, 'encuesta_campo_id');
    }

    /** Devuelve el valor legible (decodifica JSON de checkbox a "a, b, c"). */
    public function getLegibleAttribute(): string
    {
        $v = (string) $this->valor;
        if ($v !== '' && ($v[0] === '[' || $v[0] === '{')) {
            $dec = json_decode($v, true);
            if (is_array($dec)) return implode(', ', $dec);
        }
        return $v;
    }
}
