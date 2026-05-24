<?php

namespace App\Models;

use App\Scopes\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 📞 Llamada WhatsApp Business Calling API.
 */
class WhatsappCall extends Model
{
    protected $table = 'whatsapp_calls';

    public const ESTADO_REQUESTED     = 'requested';
    public const ESTADO_RINGING       = 'ringing';
    public const ESTADO_CONNECTING    = 'connecting';
    public const ESTADO_CONNECTED     = 'connected';
    public const ESTADO_ENDED         = 'ended';
    public const ESTADO_FAILED        = 'failed';
    public const ESTADO_REJECTED      = 'rejected';
    public const ESTADO_NO_PERMISSION = 'no_permission';

    public const DIR_OUT = 'outbound';
    public const DIR_IN  = 'inbound';

    protected $fillable = [
        'tenant_id', 'conversacion_id', 'operador_user_id', 'cliente_id',
        'telefono', 'direccion', 'call_id', 'phone_number_id',
        'estado', 'motivo_termino',
        'sdp_offer', 'sdp_answer',
        'requested_at', 'ringing_at', 'connected_at', 'ended_at', 'duracion_seg',
        'costo_usd', 'moneda',
        'meta_payload', 'error_msg',
    ];

    protected $casts = [
        'meta_payload'  => 'array',
        'requested_at'  => 'datetime',
        'ringing_at'    => 'datetime',
        'connected_at'  => 'datetime',
        'ended_at'      => 'datetime',
        'duracion_seg'  => 'int',
        'costo_usd'     => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToTenant);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Calcula duración entre connected_at y ended_at en segundos. */
    public function calcularDuracion(): int
    {
        if (!$this->connected_at || !$this->ended_at) return 0;
        return max(0, $this->ended_at->diffInSeconds($this->connected_at));
    }
}
