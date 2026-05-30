<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 💡 Sugerencia del bot en MODO SHADOW (copiloto).
 *
 * El bot, aunque esté apagado, genera una respuesta SUGERIDA cuando el
 * cliente escribe. NO se envía nunca: solo se le muestra al operador en
 * el chat para que decida (usar / editar / ignorar). Cada decisión mide
 * la precisión del bot y sirve para saber cuándo está listo para soltarlo.
 */
class BotSugerencia extends Model
{
    protected $table = 'bot_sugerencias';

    protected $fillable = [
        'tenant_id', 'conversacion_id', 'mensaje_cliente_id',
        'sugerencia', 'respuesta_operador', 'estado', 'similitud', 'decidido_at',
    ];

    protected $casts = [
        'similitud'   => 'integer',
        'decidido_at' => 'datetime',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_USADA     = 'usada';      // operador la envió tal cual
    public const ESTADO_EDITADA   = 'editada';    // operador la modificó antes de enviar
    public const ESTADO_IGNORADA  = 'ignorada';   // operador escribió otra cosa

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }
}
