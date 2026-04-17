<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocion extends Model
{
    use SoftDeletes;

    protected $table = 'promociones';

    public const TIPO_PORCENTAJE       = 'porcentaje';
    public const TIPO_MONTO_FIJO       = 'monto_fijo';
    public const TIPO_PRECIO_ESPECIAL  = 'precio_especial';
    public const TIPO_NX1              = 'nx1';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'valor',
        'compra',
        'paga',
        'fecha_inicio',
        'fecha_fin',
        'imagen_url',
        'codigo_cupon',
        'activa',
        'aplica_todos_productos',
        'aplica_todas_sedes',
        'orden',
    ];

    protected $casts = [
        'valor'                  => 'decimal:2',
        'fecha_inicio'           => 'datetime',
        'fecha_fin'              => 'datetime',
        'activa'                 => 'boolean',
        'aplica_todos_productos' => 'boolean',
        'aplica_todas_sedes'     => 'boolean',
        'compra'                 => 'integer',
        'paga'                   => 'integer',
        'orden'                  => 'integer',
    ];

    protected static function booted(): void
    {
        $clean = fn () => app(\App\Services\BotCatalogoService::class)->limpiarCache();
        static::saved($clean);
        static::deleted($clean);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'promocion_producto')
            ->withTimestamps();
    }

    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'promocion_sede')
            ->withTimestamps();
    }

    public function scopeVigentes($query)
    {
        $hoy = Carbon::now();

        return $query->where('activa', true)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy);
            })
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy);
            });
    }

    public function estaVigente(): bool
    {
        if (!$this->activa) {
            return false;
        }

        $hoy = Carbon::now();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio)) {
            return false;
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin)) {
            return false;
        }

        return true;
    }

    public function descripcionCorta(): string
    {
        return match ($this->tipo) {
            self::TIPO_PORCENTAJE      => "{$this->valor}% de descuento",
            self::TIPO_MONTO_FIJO      => "Descuento de \${$this->valor}",
            self::TIPO_PRECIO_ESPECIAL => "Precio especial \${$this->valor}",
            self::TIPO_NX1             => "Lleva {$this->compra} paga {$this->paga}",
            default                    => $this->nombre,
        };
    }
}
