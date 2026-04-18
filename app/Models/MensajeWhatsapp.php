<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWhatsapp extends Model
{
    protected $table = 'mensajes_whatsapp';

    protected $fillable = [
        'conversacion_id',
        'rol',
        'tipo',
        'contenido',
        'meta',
        'mensaje_externo_id',
        'latencia_ms',
        'tokens_input',
        'tokens_output',
    ];

    protected $casts = [
        'meta'          => 'array',
        'latencia_ms'   => 'integer',
        'tokens_input'  => 'integer',
        'tokens_output' => 'integer',
    ];

    public const ROL_USER      = 'user';
    public const ROL_ASSISTANT = 'assistant';
    public const ROL_SYSTEM    = 'system';
    public const ROL_TOOL      = 'tool';

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }
}
