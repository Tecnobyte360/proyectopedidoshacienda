<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampanaDestinatario extends Model
{
    use BelongsToTenant;

    protected $table = 'campana_destinatarios';

    protected $fillable = [
        'campana_id', 'tenant_id', 'cliente_id',
        'nombre', 'telefono',
        'estado', 'mensaje_renderizado', 'enviado_at', 'error_detalle', 'intentos',
        'respondio_at', 'respuestas_count',
        // 📊 Tracking marketing
        'mensaje_externo_id',
        'entregado_at', 'leido_at',
        'boton_click', 'boton_click_at', 'botones_clicks',
        'reaccion', 'reaccion_at',
        'pedido_id', 'pedido_at',
    ];

    protected $casts = [
        'enviado_at'       => 'datetime',
        'respondio_at'     => 'datetime',
        'entregado_at'     => 'datetime',
        'leido_at'         => 'datetime',
        'boton_click_at'   => 'datetime',
        'botones_clicks'   => 'array',
        'reaccion_at'      => 'datetime',
        'pedido_at'        => 'datetime',
        'intentos'         => 'integer',
        'respuestas_count' => 'integer',
        'pedido_id'        => 'integer',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ENVIADO   = 'enviado';
    public const ESTADO_FALLIDO   = 'fallido';
    public const ESTADO_OMITIDO   = 'omitido';

    public function campana(): BelongsTo
    {
        return $this->belongsTo(CampanaWhatsapp::class, 'campana_id');
    }
}
