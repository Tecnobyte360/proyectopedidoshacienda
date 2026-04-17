<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetallePedido extends Model
{
    use HasFactory;

    protected $table = 'detalles_pedido';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'producto',
        'codigo_producto',
        'cantidad',
        'unidad',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad'        => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'subtotal'        => 'decimal:2',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
