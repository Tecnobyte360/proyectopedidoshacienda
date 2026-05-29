<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ConversacionIvr extends Model
{
    use BelongsToTenant;

    protected $table = 'conversaciones_ivr';

    protected $fillable = [
        'llamada_id','tenant_id','asterisk_uniqueid','caller_id',
        'historial','acciones_ejecutadas','estado','turnos','costo_usd',
        'carrito','direccion_entrega','pedido_creado_id',
    ];

    protected $casts = [
        'historial'           => 'array',
        'acciones_ejecutadas' => 'array',
        'carrito'             => 'array',
        'costo_usd'           => 'decimal:4',
    ];

    public function llamada()
    {
        return $this->belongsTo(LlamadaIvr::class, 'llamada_id');
    }
}
