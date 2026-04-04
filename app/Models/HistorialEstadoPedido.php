<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialEstadoPedido extends Model
{
    use HasFactory;

    protected $table = 'historial_estados_pedido';

    protected $fillable = [
        'pedido_id',
        'estado_anterior',
        'estado_nuevo',
        'titulo',
        'descripcion',
        'usuario',
        'usuario_id',
        'fecha_evento',
    ];

    protected $casts = [
        'fecha_evento' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}