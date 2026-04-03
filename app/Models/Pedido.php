<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'sede_id',
        'fecha_pedido',
        'hora_entrega',
        'estado',
        'total',
        'notas',
        'cliente_nombre',

        // Nuevo: número desde el que llegó el WhatsApp
        'telefono_whatsapp',

        // Nuevo: número de contacto que entrega el cliente
        'telefono_contacto',

        // Campo viejo, lo dejamos por compatibilidad
        'telefono',

        'canal',
        'conversacion_completa',
        'resumen_conversacion',
    ];

    protected $casts = [
        'fecha_pedido' => 'datetime',
        'total'        => 'decimal:2',
    ];

    /* ==========================
     * RELACIONES
     * ==========================*/

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetallePedido::class);
    }
}