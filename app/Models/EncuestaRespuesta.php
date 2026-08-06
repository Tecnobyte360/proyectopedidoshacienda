<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un diligenciamiento de una encuesta (una persona que respondió).
 */
class EncuestaRespuesta extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'encuesta_respuestas';
    protected $guarded = ['id'];

    protected $casts = [
        'vista_at'      => 'datetime',
        'completada_at' => 'datetime',
    ];

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(Encuesta::class);
    }

    public function valores(): HasMany
    {
        return $this->hasMany(EncuestaRespuestaValor::class);
    }

    public function completada(): bool
    {
        return $this->completada_at !== null;
    }
}
