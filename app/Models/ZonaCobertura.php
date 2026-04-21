<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ZonaCobertura extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'zonas_cobertura';

    protected $fillable = [
        'tenant_id',
        'sede_id',
        'nombre',
        'descripcion',
        'color',
        'poligono',
        'centro_lat',
        'centro_lng',
        'area_km2',
        'costo_envio',
        'pedido_minimo',
        'tiempo_estimado_min',
        'orden',
        'activa',
    ];

    protected $casts = [
        'activa'              => 'boolean',
        'costo_envio'         => 'decimal:2',
        'pedido_minimo'       => 'decimal:2',
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
     *
     * Intenta 3 estrategias en orden:
     *   1. Match exacto del barrio normalizado (rápido).
     *   2. Match parcial — el input contiene el barrio o viceversa.
     *      Ej: "Reserva de Bucaros" matchea "Bucaros".
     *   3. Match contra el nombre de la zona directamente
     *      (por si el cliente nombró la zona en lugar del barrio).
     */
    public static function resolverPorBarrio(?string $barrio, ?int $sedeId = null): ?self
    {
        if (empty($barrio)) {
            return null;
        }

        $normalizado = self::normalizar($barrio);

        $baseScope = fn ($q) => $q->where('activa', true)
            ->when($sedeId, fn ($qq) => $qq->where(function ($qqq) use ($sedeId) {
                $qqq->where('sede_id', $sedeId)->orWhereNull('sede_id');
            }));

        // Estrategia 1: exacto
        $exacto = ZonaBarrio::query()
            ->with('zona')
            ->where('nombre_normalizado', $normalizado)
            ->whereHas('zona', $baseScope)
            ->first()?->zona;

        if ($exacto) return $exacto;

        // Estrategia 2: barrios cuyo nombre_normalizado está CONTENIDO en el input
        // Ej: input = "reserva de bucaros", barrio BD = "bucaros" → match
        // Traemos los barrios activos y filtramos en PHP (evita diferencias MySQL/SQLite)
        $candidatos = ZonaBarrio::query()
            ->with('zona')
            ->whereHas('zona', $baseScope)
            ->get();

        // 2a. El barrio BD está contenido en lo que dijo el cliente
        foreach ($candidatos as $c) {
            $barrioDb = (string) $c->nombre_normalizado;
            if ($barrioDb !== '' && str_contains($normalizado, $barrioDb)) {
                return $c->zona;
            }
        }

        // 2b. Lo que dijo el cliente está contenido en el barrio BD
        foreach ($candidatos as $c) {
            $barrioDb = (string) $c->nombre_normalizado;
            if ($barrioDb !== '' && str_contains($barrioDb, $normalizado)) {
                return $c->zona;
            }
        }

        // Estrategia 3: match contra el nombre de la zona directamente
        $zonas = self::query()
            ->where('activa', true)
            ->when($sedeId, fn ($qq) => $qq->where(function ($qqq) use ($sedeId) {
                $qqq->where('sede_id', $sedeId)->orWhereNull('sede_id');
            }))
            ->get();

        foreach ($zonas as $z) {
            $nz = self::normalizar((string) $z->nombre);
            if ($nz !== '' && (str_contains($normalizado, $nz) || str_contains($nz, $normalizado))) {
                return $z;
            }
        }

        return null;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = Str::ascii($texto);
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        return preg_replace('/\s+/', ' ', $texto);
    }
}
