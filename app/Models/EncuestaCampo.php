<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un campo (pregunta) de una encuesta. El scope de tenant lo hereda por la encuesta.
 */
class EncuestaCampo extends Model
{
    protected $table = 'encuesta_campos';
    protected $guarded = ['id'];

    protected $casts = [
        'opciones'  => 'array',
        'requerido' => 'boolean',
        'orden'     => 'integer',
    ];

    /** Tipos soportados por el constructor. */
    public const TIPOS = [
        'estrellas' => 'Calificación (estrellas)',
        'texto'     => 'Texto corto',
        'textarea'  => 'Texto largo',
        'radio'     => 'Opción única',
        'checkbox'  => 'Opción múltiple',
        'si_no'     => 'Sí / No',
        'numero'    => 'Número',
    ];

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(Encuesta::class);
    }

    public function esOpcionado(): bool
    {
        return in_array($this->tipo, ['radio', 'checkbox'], true);
    }
}
