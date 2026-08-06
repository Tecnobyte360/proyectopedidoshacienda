<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Encuesta personalizable creada por el tenant (form builder).
 */
class Encuesta extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'encuestas';
    protected $guarded = ['id'];

    protected $casts = [
        'activa' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Encuesta $e) {
            if (empty($e->token)) {
                $e->token = Str::lower(Str::random(10));
            }
        });
    }

    public function campos(): HasMany
    {
        return $this->hasMany(EncuestaCampo::class)->orderBy('orden')->orderBy('id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EncuestaRespuesta::class);
    }

    /** Respuestas efectivamente completadas. */
    public function respuestasCompletadas(): HasMany
    {
        return $this->respuestas()->whereNotNull('completada_at');
    }
}
