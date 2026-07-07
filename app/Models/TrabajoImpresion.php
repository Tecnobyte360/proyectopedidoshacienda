<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrabajoImpresion extends Model
{
    protected $table = 'trabajos_impresion';

    protected $fillable = [
        'tenant_id', 'impresora_id', 'pedido_id', 'tipo',
        'contenido', 'estado', 'intentos', 'error', 'enviado_at', 'impreso_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'impreso_at' => 'datetime',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ENVIADO   = 'enviado';
    public const ESTADO_IMPRESO   = 'impreso';
    public const ESTADO_ERROR     = 'error';

    public function impresora()
    {
        return $this->belongsTo(Impresora::class);
    }
}
