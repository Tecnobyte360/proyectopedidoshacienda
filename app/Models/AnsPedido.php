<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnsPedido extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'ans_pedidos';

    protected $fillable = [
        'tenant_id',
        'accion',
        'tiempo_minutos',
        'tiempo_alerta',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiempo_minutos' => 'integer',
        'tiempo_alerta' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS ÚTILES
    |--------------------------------------------------------------------------
    */

    public static function tiempo(string $accion): ?int
    {
        return self::where('accion', $accion)
            ->where('activo', true)
            ->value('tiempo_minutos');
    }

    public static function alerta(string $accion): ?int
    {
        return self::where('accion', $accion)
            ->where('activo', true)
            ->value('tiempo_alerta');
    }
}