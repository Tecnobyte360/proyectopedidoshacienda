<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AnsTiempoPedido extends Model
{
    protected $table = 'ans_tiempos_pedido';

    protected $fillable = [
        'estado',
        'nombre',
        'descripcion',
        'minutos_objetivo',
        'minutos_alerta',
        'minutos_critico',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo'           => 'boolean',
        'minutos_objetivo' => 'integer',
        'minutos_alerta'   => 'integer',
        'minutos_critico'  => 'integer',
        'orden'            => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Obtiene la configuración ANS para un estado dado (cacheado 5 min).
     */
    public static function paraEstado(string $estado): ?self
    {
        return Cache::remember(
            "ans_tiempo_estado_{$estado}",
            now()->addMinutes(5),
            fn () => self::where('estado', $estado)->where('activo', true)->first()
        );
    }

    public static function limpiarCache(): void
    {
        foreach (['nuevo', 'en_preparacion', 'repartidor_en_camino'] as $e) {
            Cache::forget("ans_tiempo_estado_{$e}");
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limpiarCache());
        static::deleted(fn () => self::limpiarCache());
    }
}
