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
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'intentos'   => 'integer',
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
