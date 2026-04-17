<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ZonaCobertura extends Model
{
    protected $table = 'zonas_cobertura';

    protected $fillable = [
        'sede_id',
        'nombre',
        'descripcion',
        'color',
        'poligono',
        'centro_lat',
        'centro_lng',
        'area_km2',
        'costo_envio',
        'tiempo_estimado_min',
        'orden',
        'activa',
    ];

    protected $casts = [
        'activa'              => 'boolean',
        'costo_envio'         => 'decimal:2',
        'tiempo_estimado_min' => 'integer',
        'orden'               => 'integer',
        'poligono'            => 'array',
        'centro_lat'          => 'float',
        'centro_lng'          => 'float',
        'area_km2'            => 'float',
    ];

    protected static function booted(): void
    {
        $clean = fn () => app(\App\Services\BotCatalogoService::class)->limpiarCache();
        static::saved($clean);
        static::deleted($clean);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function barrios(): HasMany
    {
        return $this->hasMany(ZonaBarrio::class, 'zona_cobertura_id');
    }

    public function domiciliarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Domiciliario::class,
            'domiciliario_zona_cobertura',
            'zona_cobertura_id',
            'domiciliario_id'
        )->withTimestamps();
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'zona_cobertura_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Resuelve la zona de cobertura a partir del nombre de un barrio.
     * Compara normalizado (sin acentos, lowercase, sin espacios extras).
     */
    public static function resolverPorBarrio(?string $barrio, ?int $sedeId = null): ?self
    {
        if (empty($barrio)) {
            return null;
        }

        $normalizado = self::normalizar($barrio);

        $query = ZonaBarrio::query()
            ->with('zona')
            ->where('nombre_normalizado', $normalizado)
            ->whereHas('zona', fn ($q) => $q->where('activa', true)
                ->when($sedeId, fn ($qq) => $qq->where(function ($qqq) use ($sedeId) {
                    $qqq->where('sede_id', $sedeId)->orWhereNull('sede_id');
                })));

        return $query->first()?->zona;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = Str::ascii($texto);
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        return preg_replace('/\s+/', ' ', $texto);
    }
}
