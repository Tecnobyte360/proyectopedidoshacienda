<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'precio_mensual',
        'precio_anual',
        'moneda',
        'max_pedidos_mes',
        'max_usuarios',
        'max_sedes',
        'max_productos',
        'max_clientes',
        'feature_whatsapp',
        'feature_ia',
        'feature_reportes',
        'feature_multi_sede',
        'feature_api',
        'activo',
        'publico',
        'orden',
        'caracteristicas_extra',
    ];

    protected $casts = [
        'precio_mensual'         => 'decimal:2',
        'precio_anual'           => 'decimal:2',
        'feature_whatsapp'       => 'boolean',
        'feature_ia'             => 'boolean',
        'feature_reportes'       => 'boolean',
        'feature_multi_sede'     => 'boolean',
        'feature_api'            => 'boolean',
        'activo'                 => 'boolean',
        'publico'                => 'boolean',
        'caracteristicas_extra'  => 'array',
        'orden'                  => 'integer',
    ];

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function precioFormateado(string $ciclo = 'mensual'): string
    {
        $precio = $ciclo === 'anual' ? $this->precio_anual : $this->precio_mensual;
        if ($precio == 0) return 'Gratis';
        return '$' . number_format($precio, 0, ',', '.') . ' ' . $this->moneda;
    }

    public function ahorroAnual(): ?float
    {
        if ($this->precio_anual <= 0 || $this->precio_mensual <= 0) return null;
        $costoSinDescuento = $this->precio_mensual * 12;
        return $costoSinDescuento - $this->precio_anual;
    }
}
