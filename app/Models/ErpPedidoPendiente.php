<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ErpPedidoPendiente extends Model
{
    use BelongsToTenant;

    protected $table = 'erp_pedidos_pendientes';

    public const TIPO_CLIENTE_CREAR = 'cliente_crear';
    public const TIPO_PEDIDO_EXPORT = 'pedido_export';

    public const ESTADO_PENDIENTE   = 'pendiente';
    public const ESTADO_PROCESANDO  = 'procesando';
    public const ESTADO_COMPLETADO  = 'completado';
    public const ESTADO_FALLIDO_MAX = 'fallido_max';
    public const ESTADO_DESCARTADO  = 'descartado';

    protected $fillable = [
        'tenant_id', 'integracion_id', 'conversacion_id', 'pedido_id',
        'tipo', 'telefono', 'payload',
        'estado', 'intentos', 'max_intentos',
        'ultimo_error', 'ultimo_intento_at', 'proximo_intento_at',
        'completado_at',
    ];

    protected $casts = [
        'payload'             => 'array',
        'ultimo_intento_at'   => 'datetime',
        'proximo_intento_at'  => 'datetime',
        'completado_at'       => 'datetime',
    ];

    public function integracion()
    {
        return $this->belongsTo(Integracion::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function conversacion()
    {
        return $this->belongsTo(ConversacionWhatsapp::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
