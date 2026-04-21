<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes, \App\Models\Concerns\BelongsToTenant;

    protected $table = 'productos';

    protected $fillable = [
        'tenant_id',
        'categoria_id',
        'codigo',
        'nombre',
        'descripcion',
        'descripcion_corta',
        'unidad',
        'precio_base',
        'imagen_url',
        'imagen_path',
        'palabras_clave',
        'activo',
        'destacado',
        'orden',
    ];

    protected $casts = [
        'palabras_clave' => 'array',
        'activo'         => 'boolean',
        'destacado'      => 'boolean',
        'precio_base'    => 'decimal:2',
        'orden'          => 'integer',
    ];

    protected static function booted(): void
    {
        $clean = fn () => app(\App\Services\BotCatalogoService::class)->limpiarCache();
        static::saved($clean);
        static::deleted($clean);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ProductoCategoria::class, 'categoria_id');
    }

    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'producto_sede')
            ->withPivot(['precio', 'disponible', 'nota_sede'])
            ->withTimestamps();
    }

    public function promociones(): BelongsToMany
    {
        return $this->belongsToMany(Promocion::class, 'promocion_producto')
            ->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDestacados($query)
    {
        return $query->where('destacado', true);
    }

    public function scopeDisponibleEnSede($query, int $sedeId)
    {
        return $query->whereHas('sedes', function ($q) use ($sedeId) {
            $q->where('sede_id', $sedeId)->where('disponible', true);
        });
    }

    /**
     * Devuelve el precio del producto para una sede dada.
     * Si la sede tiene precio personalizado, lo usa. Si no, usa el precio_base.
     */
    public function precioParaSede(?int $sedeId): float
    {
        if (!$sedeId) {
            return (float) $this->precio_base;
        }

        $pivot = $this->sedes->firstWhere('id', $sedeId)?->pivot;

        if ($pivot && $pivot->precio !== null) {
            return (float) $pivot->precio;
        }

        return (float) $this->precio_base;
    }

    /**
     * URL pública de la imagen del producto.
     * Prioridad: imagen_path (storage local) → imagen_url (externa) → null.
     */
    public function urlImagen(): ?string
    {
        if (!empty($this->imagen_path)) {
            return asset('storage/' . ltrim($this->imagen_path, '/'));
        }

        return !empty($this->imagen_url) ? $this->imagen_url : null;
    }

    public function disponibleEnSede(?int $sedeId): bool
    {
        if (!$sedeId) {
            return (bool) $this->activo;
        }

        $pivot = $this->sedes->firstWhere('id', $sedeId)?->pivot;

        return $this->activo && ($pivot ? (bool) $pivot->disponible : false);
    }
}
