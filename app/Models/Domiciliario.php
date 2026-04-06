<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Domiciliario extends Model
{
    use HasFactory;

    protected $table = 'domiciliarios';

    protected $fillable = [
        'nombre',
        'telefono',
        'placa',
        'vehiculo',
        'activo',
        'domiciliario_id',
        'fecha_asignacion_domiciliario',
        'fecha_salida_domiciliario',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_asignacion_domiciliario' => 'datetime',
        'fecha_salida_domiciliario' => 'datetime',
    ];

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }
}
