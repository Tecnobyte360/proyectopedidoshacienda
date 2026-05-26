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

    /** Tipos de negocio soportados (para personalizar prompts y plantillas). */
    public const TIPOS_NEGOCIO = [
        'restaurante'   => '🍽️ Restaurante / Comida',
        'carniceria'    => '🥩 Carnicería / Carnes',
        'panaderia'     => '🥐 Panadería / Repostería',
        'tienda'        => '🛒 Tienda / Minimarket',
        'farmacia'      => '💊 Farmacia / Droguería',
        'ferreteria'    => '🔧 Ferretería',
        'distribuidora' => '📦 Distribuidora / Mayorista',
        'servicios'     => '🛠️ Servicios profesionales',
        'manufactura'   => '🏭 Manufactura / Producción',
        'otro'          => '🏢 Otro',
    ];

    protected $fillable = [
        'nombre',
        'slug',
        'logo_url',
        'favicon_url',
        'plan',
        'activo',
        'suspendido_por_mora',
        'suspendido_at',
        'requiere_2fa',
        'requiere_2fa_desde',
        'gracia_2fa_dias',
        'trial_ends_at',
        'subscription_ends_at',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'ciudad',
        'tipo_negocio',
        'slogan',
        'descripcion_negocio',
        'google_maps_api_key',
        'google_maps_activo',
        'google_maps_centro_lat',
        'google_maps_centro_lng',
        'google_maps_zoom',
        'google_maps_server_api_key',
        'color_primario',
        'color_secundario',
        'openai_api_key',
        'anthropic_api_key',
        'whatsapp_config',
        'whatsapp_provider',
        'whatsapp_fallback_enabled',
        'wompi_config',
        'wompi_modo',
        'notas_internas',
        // 📷 Instagram DMs
        'instagram_business_account_id',
        'instagram_page_id',
        'instagram_activo',
    ];

    public const WA_PROVIDER_AUTO      = 'auto';
    public const WA_PROVIDER_META      = 'meta';
    public const WA_PROVIDER_TECNOBYTE = 'tecnobyte';

    public const WA_PROVIDERS = [
        self::WA_PROVIDER_AUTO      => '⚡ Automático (Meta si está activo, sino TecnoByteApp)',
        self::WA_PROVIDER_META      => '🟢 Meta WhatsApp Cloud API (oficial)',
        self::WA_PROVIDER_TECNOBYTE => '🟡 TecnoByteApp (no oficial)',
    ];

    protected $casts = [
        'instagram_activo'     => 'boolean',
        'activo'               => 'boolean',
        'suspendido_por_mora'  => 'boolean',
        'suspendido_at'        => 'datetime',
        'requiere_2fa'         => 'boolean',
        'requiere_2fa_desde'   => 'datetime',
        'gracia_2fa_dias'      => 'integer',
        'trial_ends_at'        => 'date',
        'subscription_ends_at' => 'date',
        'whatsapp_config'      => 'array',
        // 🔐 wompi_config se cifra como JSON. Las 4 llaves de Wompi viven aquí
        //    para no exponer cada una como columna independiente.
        'wompi_config'         => 'encrypted:array',
        'openai_api_key'       => \App\Casts\EncryptedTolerante::class,
        'anthropic_api_key'    => \App\Casts\EncryptedTolerante::class,
        'google_maps_api_key'  => \App\Casts\EncryptedTolerante::class,
        'google_maps_server_api_key' => \App\Casts\EncryptedTolerante::class,
        'google_maps_activo'   => 'boolean',
        'google_maps_centro_lat' => 'float',
        'google_maps_centro_lng' => 'float',
        'google_maps_zoom'     => 'integer',
    ];

    protected $hidden = [
        'openai_api_key',
        'anthropic_api_key',
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

        // 📁 Crear carpetas de storage automáticamente al crear el tenant
        static::created(function ($tenant) {
            try {
                $tenant->crearCarpetasStorage();
            } catch (\Throwable $e) {
                \Log::warning("No se pudieron crear carpetas storage para tenant {$tenant->slug}: " . $e->getMessage());
            }
        });
    }

    /**
     * Crea la estructura de carpetas dedicada del tenant en storage/app/public.
     * Idempotente: si ya existen, no las recrea.
     *
     *  tenants/{slug}/
     *    ├── campanas/       (imágenes de campañas WhatsApp)
     *    ├── productos/      (imágenes de productos)
     *    ├── logos/          (logo + favicon del branding)
     *    ├── pedidos/        (adjuntos/comprobantes de pedidos)
     *    └── otros/          (catch-all para futuros módulos)
     */
    public function crearCarpetasStorage(): void
    {
        if (!$this->slug) return;

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $base = "tenants/{$this->slug}";

        $subcarpetas = ['campanas', 'productos', 'logos', 'pedidos', 'otros'];

        foreach ($subcarpetas as $sub) {
            $ruta = "{$base}/{$sub}";
            if (!$disk->exists($ruta)) {
                $disk->makeDirectory($ruta);
                // .gitkeep para que sobreviva a operaciones de git si se versionan
                $disk->put("{$ruta}/.gitkeep", '');

                // 🔐 Asegurar que www-data pueda escribir (Laravel ejecuta como
                // www-data via PHP-FPM, pero el comando CLI puede correr como root).
                // Sin esto, los uploads desde la app fallan silenciosamente.
                $rutaAbs = $disk->path($ruta);
                @chown($rutaAbs, 'www-data');
                @chgrp($rutaAbs, 'www-data');
                @chmod($rutaAbs, 0775);
            }
        }
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
     * ¿Está activo? (solo verifica suspensión administrativa manual)
     *
     * NOTA: La fecha subscription_ends_at NO bloquea aquí — el cron de morosidad
     * usa días de gracia configurables y activa tenant.suspendido_por_mora=true
     * cuando se acaba el plazo. El middleware BloqueaSiMoroso se encarga de
     * redirigir a /billing/expirado. Aquí solo bloqueamos suspensión manual.
     */
    public function tieneAccesoActivo(): bool
    {
        return (bool) $this->activo;
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

    /**
     * Retorna la Anthropic API key del tenant (con fallback al .env).
     */
    public function anthropicApiKey(): ?string
    {
        try {
            $propia = trim((string) $this->anthropic_api_key);
        } catch (\Throwable $e) {
            $propia = '';
        }
        if ($propia !== '') return $propia;
        $global = trim((string) env('ANTHROPIC_API_KEY'));
        return $global !== '' ? $global : null;
    }

    public static function resolverAnthropicKey(): ?string
    {
        $tenant = app(\App\Services\TenantManager::class)->current();
        if ($tenant) {
            return $tenant->anthropicApiKey();
        }
        $global = trim((string) env('ANTHROPIC_API_KEY'));
        return $global !== '' ? $global : null;
    }

    /* ─── WHATSAPP PROVIDER ─────────────────────────────────────────── */

    /**
     * Devuelve qué proveedor de WhatsApp debe usar este tenant para envíos
     * salientes: 'meta' | 'tecnobyte'. Resuelve 'auto' consultando si hay
     * config Meta activa.
     */
    public function proveedorWhatsappResuelto(): string
    {
        $eleccion = $this->whatsapp_provider ?: self::WA_PROVIDER_AUTO;

        if ($eleccion === self::WA_PROVIDER_META) {
            return self::WA_PROVIDER_META;
        }
        if ($eleccion === self::WA_PROVIDER_TECNOBYTE) {
            return self::WA_PROVIDER_TECNOBYTE;
        }

        // AUTO: Meta si hay config activa, sino TecnoByteApp
        try {
            $hayMeta = \App\Models\MetaWhatsappConfig::query()
                ->where('tenant_id', $this->id)
                ->where('activo', true)
                ->exists();
            return $hayMeta ? self::WA_PROVIDER_META : self::WA_PROVIDER_TECNOBYTE;
        } catch (\Throwable $e) {
            return self::WA_PROVIDER_TECNOBYTE;
        }
    }

    public function fallbackWhatsappHabilitado(): bool
    {
        return (bool) ($this->whatsapp_fallback_enabled ?? true);
    }

    /* ─── WOMPI (pagos) ──────────────────────────────────────────────── */

    /**
     * Retorna las 4 llaves de Wompi del tenant como array.
     * Si no hay nada configurado devuelve null.
     */
    public function wompiCredenciales(): ?array
    {
        try {
            $cfg = $this->wompi_config;
        } catch (\Throwable $e) {
            return null; // columna no existe o no se pudo descifrar
        }
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
