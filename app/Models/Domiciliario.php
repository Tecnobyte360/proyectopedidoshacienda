<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domiciliario extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $table = 'domiciliarios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'pais_codigo',
        'telefono',
        'placa',
        'vehiculo',
        'estado',
        'activo',
        'token_acceso',
        'lat_actual',
        'lng_actual',
        'ubicacion_actualizada_at',
    ];

    protected $casts = [
        'activo'                   => 'boolean',
        'lat_actual'               => 'float',
        'lng_actual'               => 'float',
        'ubicacion_actualizada_at' => 'datetime',
    ];

    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_EN_RUTA    = 'en_ruta';
    public const ESTADO_OCUPADO    = 'ocupado';

    protected static function booted(): void
    {
        // Si se crea un domiciliario sin estado, lo ponemos disponible por defecto
        static::creating(function ($d) {
            if (empty($d->estado)) {
                $d->estado = self::ESTADO_DISPONIBLE;
            }
            if ($d->activo === null) {
                $d->activo = true;
            }
            if (empty($d->token_acceso)) {
                $d->token_acceso = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * URL del portal personal del domiciliario (sin auth, basado en token).
     * El domiciliario lo abre en su celular y ve sus pedidos en tiempo real.
     */
    public function urlPortal(): string
    {
        $path = "/d/{$this->token_acceso}";
        $tenant = $this->tenant_id
            ? app(\App\Services\TenantManager::class)->withoutTenant(
                fn () => \App\Models\Tenant::find($this->tenant_id)
              )
            : null;
        if ($tenant && !empty($tenant->slug)) {
            $base = config('app.tenant_base_domain', 'tecnobyte360.com');
            $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
            return "{$scheme}://{$tenant->slug}.{$base}{$path}";
        }
        return url($path);
    }

    public const PAISES = [
        ['codigo' => '+57',  'nombre' => 'Colombia',       'flag' => '🇨🇴'],
        ['codigo' => '+1',   'nombre' => 'Estados Unidos', 'flag' => '🇺🇸'],
        ['codigo' => '+52',  'nombre' => 'México',         'flag' => '🇲🇽'],
        ['codigo' => '+34',  'nombre' => 'España',         'flag' => '🇪🇸'],
        ['codigo' => '+51',  'nombre' => 'Perú',           'flag' => '🇵🇪'],
        ['codigo' => '+593', 'nombre' => 'Ecuador',        'flag' => '🇪🇨'],
        ['codigo' => '+58',  'nombre' => 'Venezuela',      'flag' => '🇻🇪'],
        ['codigo' => '+54',  'nombre' => 'Argentina',      'flag' => '🇦🇷'],
        ['codigo' => '+56',  'nombre' => 'Chile',          'flag' => '🇨🇱'],
        ['codigo' => '+55',  'nombre' => 'Brasil',         'flag' => '🇧🇷'],
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function zonas(): BelongsToMany
    {
        return $this->belongsToMany(
            ZonaCobertura::class,
            'domiciliario_zona_cobertura',
            'domiciliario_id',
            'zona_cobertura_id'
        )->withTimestamps();
    }

    public function scopeDisponibles($query)
    {
        return $query->where('activo', true)->where('estado', 'disponible');
    }

    /**
     * Teléfono completo internacional sin signos: 573001234567
     */
    public function telefonoInternacional(): ?string
    {
        if (empty($this->telefono)) {
            return null;
        }

        $codigo = preg_replace('/\D/', '', (string) $this->pais_codigo);
        $numero = preg_replace('/\D/', '', (string) $this->telefono);

        return $codigo . $numero;
    }

    /**
     * Teléfono formateado para mostrar: +57 300 123 4567
     */
    public function telefonoFormateado(): ?string
    {
        if (empty($this->telefono)) {
            return null;
        }

        return trim(($this->pais_codigo ?? '+57') . ' ' . $this->telefono);
    }

    public function whatsappUrl(): ?string
    {
        $tel = $this->telefonoInternacional();
        return $tel ? "https://wa.me/{$tel}" : null;
    }
}
