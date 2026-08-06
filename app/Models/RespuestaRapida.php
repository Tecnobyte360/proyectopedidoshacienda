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

    protected $fillable = [
        'tenant_id', 'atajo', 'texto', 'orden', 'activa',
        'adjunto_path', 'adjunto_nombre', 'adjunto_mime', 'adjunto_tipo',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];

    public function tieneAdjunto(): bool
    {
        return !empty($this->adjunto_path);
    }

    /** URL pública del adjunto (para mostrarlo/enviarlo). */
    public function getAdjuntoUrlAttribute(): ?string
    {
        return $this->adjunto_path
            ? rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\Storage::url($this->adjunto_path)
            : null;
    }
}
