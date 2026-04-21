<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'nombre',
        'slug',
        'logo_url',
        'plan',
        'activo',
        'trial_ends_at',
        'subscription_ends_at',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'color_primario',
        'color_secundario',
        'openai_api_key',
        'whatsapp_config',
        'notas_internas',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'trial_ends_at'        => 'date',
        'subscription_ends_at' => 'date',
        'whatsapp_config'      => 'array',
    ];

    protected $hidden = [
        'openai_api_key',
        'whatsapp_config',
    ];

    public const PLAN_BASICO  = 'basico';
    public const PLAN_PRO     = 'pro';
    public const PLAN_EMPRESA = 'empresa';

    protected static function booted(): void
    {
        // Auto-generar slug si no viene
        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $base = Str::slug($tenant->nombre);
                $slug = $base;
                $i = 1;
                while (self::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $tenant->slug = $slug;
            }
        });

        // Limpiar caché del mapa connection_id → tenant cuando se edita
        static::saved(fn () => app(\App\Services\WhatsappResolverService::class)->limpiarCache());
        static::deleted(fn () => app(\App\Services\WhatsappResolverService::class)->limpiarCache());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function suscripcionActiva(): ?Suscripcion
    {
        return $this->suscripciones()
            ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
            ->orderByDesc('id')
            ->first();
    }

    public function planActual(): ?Plan
    {
        return $this->suscripcionActiva()?->plan;
    }

    /**
     * ¿Está activo y dentro de su período de subscripción?
     */
    public function tieneAccesoActivo(): bool
    {
        if (!$this->activo) return false;

        if ($this->subscription_ends_at && $this->subscription_ends_at->isPast()) {
            // Verificar si está en trial
            if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
                return true;
            }
            return false;
        }

        return true;
    }

    public function estaEnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function estaSuspendido(): bool
    {
        return !$this->activo;
    }

    /**
     * URL del subdominio para este tenant (futuro).
     */
    public function dominio(): string
    {
        return $this->slug . '.' . config('app.tenant_base_domain', 'tecnobyte360.com');
    }
}
