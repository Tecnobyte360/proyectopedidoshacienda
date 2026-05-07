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
        'ack',
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
        'ack'           => 'integer',
        'latencia_ms'   => 'integer',
        'tokens_input'  => 'integer',
        'tokens_output' => 'integer',
    ];

    public const ACK_PENDING   = 0;
    public const ACK_SENT      = 1;
    public const ACK_DELIVERED = 2;
    public const ACK_READ      = 3;
    public const ACK_PLAYED    = 4;

    public const ROL_USER      = 'user';
    public const ROL_ASSISTANT = 'assistant';

    /**
     * 🛡️ PROTECCIÓN: trunca contenido y meta gigantes ANTES de guardar.
     * Evita que mensajes con tool_results enormes (catálogos, etc.) inflen
     * la BD y posteriormente causen rate_limit_exceeded en OpenAI.
     */
    protected static function booted(): void
    {
        static::saving(function ($msg) {
            $maxContent = 5000; // 5k chars por mensaje (~1.250 tokens)
            $maxMeta    = 3000; // 3k chars de metadata

            if (is_string($msg->contenido) && mb_strlen($msg->contenido) > $maxContent) {
                $msg->contenido = mb_substr($msg->contenido, 0, $maxContent) . ' …[truncado]';
            }

            // El meta es array (cast). Si serializado supera el límite, lo recortamos
            if (is_array($msg->meta)) {
                $serialized = json_encode($msg->meta);
                if ($serialized && mb_strlen($serialized) > $maxMeta) {
                    // Mantener solo claves esenciales + marca de truncado
                    $msg->meta = [
                        'tipo' => $msg->meta['tipo'] ?? 'data_truncada',
                        'truncado_at' => now()->toDateTimeString(),
                        'tamano_original_kb' => round(mb_strlen($serialized) / 1024, 1),
                    ];
                }
            }
        });
    }
    public const ROL_SYSTEM    = 'system';
    public const ROL_TOOL      = 'tool';

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }
}
