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
        'favicon_url',
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
        'wompi_config',
        'wompi_modo',
        'notas_internas',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'trial_ends_at'        => 'date',
        'subscription_ends_at' => 'date',
        'whatsapp_config'      => 'array',
        // 🔐 wompi_config se cifra como JSON. Las 4 llaves de Wompi viven aquí
        //    para no exponer cada una como columna independiente.
        'wompi_config'         => 'encrypted:array',
        'openai_api_key'       => \App\Casts\EncryptedTolerante::class,
    ];

    protected $hidden = [
        'openai_api_key',
        'whatsapp_config',
        'wompi_config',
    ];

    public const PLAN_BASICO  = 'basico';
    public const PLAN_PRO     = 'pro';
    public const PLAN_EMPRESA = 'empresa';

    /**
     * Normaliza un slug a formato DNS-válido (kebab-case).
     * Let's Encrypt y los estándares DNS NO aceptan: _ . espacios mayúsculas.
     * Solo se permite: a-z, 0-9, y "-" (no al inicio/fin).
     */
    public static function normalizarSlug(string $valor): string
    {
        // Str::slug ya pasa a minúsculas y reemplaza espacios/acentos por "-"
        $slug = Str::slug($valor, '-');
        // Por si quedaron guiones bajos (Str::slug los preserva en algunos casos)
        $slug = str_replace('_', '-', $slug);
        // Colapsar guiones repetidos
        $slug = preg_replace('/-+/', '-', $slug);
        // Trim de "-" en bordes
        $slug = trim($slug, '-');
        return $slug;
    }

    protected static function booted(): void
    {
        // Auto-generar / normalizar slug
        static::saving(function ($tenant) {
            // Si no viene slug, generar desde nombre
            if (empty($tenant->slug)) {
                $tenant->slug = self::normalizarSlug($tenant->nombre);
            } else {
                // Si viene, normalizarlo (por si trae _ . espacios MAYÚSC.)
                $tenant->slug = self::normalizarSlug($tenant->slug);
            }

            // Garantizar unicidad en INSERT
            if (!$tenant->exists) {
                $base = $tenant->slug;
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

    /**
     * Retorna la OpenAI API key que debe usar este tenant:
     *   1. La suya propia (tenants.openai_api_key) si está configurada
     *   2. Si no, la global del .env (OPENAI_API_KEY) como fallback
     *
     * Permite que cada cliente use su propio billing de OpenAI,
     * y que TecnoByte360 provea una key por defecto para el MVP.
     */
    public function openaiApiKey(): ?string
    {
        $propia = trim((string) $this->openai_api_key);
        if ($propia !== '') {
            return $propia;
        }
        $global = trim((string) env('OPENAI_API_KEY'));
        return $global !== '' ? $global : null;
    }

    /**
     * Helper estático: obtiene la key del tenant ACTUAL (via TenantManager)
     * con fallback al .env. Útil desde controllers/services.
     *
     *   $key = Tenant::resolverOpenaiKey();
     */
    public static function resolverOpenaiKey(): ?string
    {
        $tenant = app(\App\Services\TenantManager::class)->current();
        if ($tenant) {
            return $tenant->openaiApiKey();
        }
        $global = trim((string) env('OPENAI_API_KEY'));
        return $global !== '' ? $global : null;
    }

    /* ─── WOMPI (pagos) ──────────────────────────────────────────────── */

    /**
     * Retorna las 4 llaves de Wompi del tenant como array.
     * Si no hay nada configurado devuelve null.
     */
    public function wompiCredenciales(): ?array
    {
        $cfg = $this->wompi_config;
        if (!is_array($cfg)) return null;

        $publica    = trim((string) ($cfg['public_key'] ?? ''));
        $privada    = trim((string) ($cfg['private_key'] ?? ''));
        $eventos    = trim((string) ($cfg['events_secret'] ?? ''));
        $integridad = trim((string) ($cfg['integrity_secret'] ?? ''));

        if ($publica === '' && $privada === '') return null;

        return [
            'public_key'       => $publica,
            'private_key'      => $privada,
            'events_secret'    => $eventos,
            'integrity_secret' => $integridad,
            'modo'             => $this->wompi_modo ?: 'sandbox',
        ];
    }

    /** ¿El tenant tiene Wompi configurado y listo para cobrar? */
    public function tieneWompi(): bool
    {
        $c = $this->wompiCredenciales();
        return $c !== null && $c['public_key'] !== '' && $c['integrity_secret'] !== '';
    }
}
