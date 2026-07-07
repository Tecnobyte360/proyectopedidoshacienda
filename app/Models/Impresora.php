<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Impresora extends Model
{
    protected $table = 'impresoras';

    protected $fillable = [
        'tenant_id', 'nombre', 'printer_name', 'token',
        'pc_nombre', 'activa', 'ultima_conexion_at',
    ];

    protected $casts = [
        'activa'             => 'boolean',
        'ultima_conexion_at' => 'datetime',
    ];

    public function trabajos()
    {
        return $this->hasMany(TrabajoImpresion::class);
    }

    /** ¿El agente ha reportado en los últimos 60s? (en línea) */
    public function enLinea(): bool
    {
        return $this->ultima_conexion_at
            && $this->ultima_conexion_at->gt(now()->subSeconds(60));
    }

    public static function generarToken(): string
    {
        return Str::random(48);
    }
}
