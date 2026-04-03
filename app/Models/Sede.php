<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
   protected $fillable = [
        'nombre',
        'direccion',
        'hora_apertura',
        'hora_cierre',
        'activa'
    ];
}
