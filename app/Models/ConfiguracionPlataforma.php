<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración global de la PLATAFORMA (TecnoByte360 dueño del SaaS).
 * Singleton: siempre hay UNA fila (id=1).
 *
 * Distinto de ConfiguracionBot (que es por tenant) y de Tenant (que es por
 * cliente). Esta tabla guarda branding del super-admin: nombre que aparece
 * en el sidebar cuando no hay tenant impersonado, colores de la plataforma,
 * logo, etc.
 */
class ConfiguracionPlataforma extends Model
{
    protected $table = 'configuracion_plataforma';

    protected $fillable = [
        'nombre',
        'subtitulo',
        'color_primario',
        'color_secundario',
        'logo_url',
        'favicon_url',
        'email_soporte',
        'telefono_soporte',
        'sitio_web',
        'whatsapp_admin_email',
        'whatsapp_admin_password',
        'whatsapp_api_base_url',
        // 💳 Credenciales Wompi del dueño Kivox para cobrar a los tenants
        'saas_wompi_modo',
        'saas_wompi_public_key',
        'saas_wompi_private_key',
        'saas_wompi_integrity_secret',
        'saas_wompi_events_secret',
        'saas_wompi_redirect_url',
        // ⚙️ Política de cobros SaaS
        'saas_dias_antes_factura',
        'saas_dias_gracia',
        'saas_horas_envio',
        'saas_aviso_preaviso',
        'saas_aviso_vence_hoy',
        'saas_aviso_vencio_ayer',
        'saas_aviso_urgencia',
        'saas_mensaje_factura',
        'saas_mensaje_suspendido',
        'saas_billing_activo',
    ];

    protected $casts = [
        'saas_aviso_preaviso'      => 'boolean',
        'saas_aviso_vence_hoy'     => 'boolean',
        'saas_aviso_vencio_ayer'   => 'boolean',
        'saas_aviso_urgencia'      => 'boolean',
        'saas_billing_activo'      => 'boolean',
        'saas_dias_antes_factura'  => 'integer',
        'saas_dias_gracia'         => 'integer',
        'saas_horas_envio'         => 'array',
    ];

    protected $hidden = [
        'whatsapp_admin_password',
        'saas_wompi_private_key',
        'saas_wompi_integrity_secret',
        'saas_wompi_events_secret',
    ];

    /** ¿Tenemos credenciales Wompi suficientes para cobrar a tenants? */
    public function tieneWompiSaas(): bool
    {
        return !empty($this->saas_wompi_public_key)
            && !empty($this->saas_wompi_integrity_secret);
    }

    /** Devuelve credenciales Wompi del SaaS en formato del service. */
    public function wompiSaasCredenciales(): ?array
    {
        if (!$this->tieneWompiSaas()) return null;
        return [
            'modo'             => $this->saas_wompi_modo ?: 'sandbox',
            'public_key'       => $this->saas_wompi_public_key,
            'private_key'      => $this->saas_wompi_private_key,
            'integrity_secret' => $this->saas_wompi_integrity_secret,
            'events_secret'    => $this->saas_wompi_events_secret,
            'redirect_url'     => $this->saas_wompi_redirect_url ?: 'https://admin.kivox.co/billing/gracias',
        ];
    }

    /** Cache la fila para no leerla en cada request */
    public static function actual(): self
    {
        return Cache::remember('config_plataforma_actual', 300, function () {
            return self::firstOrCreate(['id' => 1], [
                'nombre'           => 'TecnoByte360',
                'subtitulo'        => 'Plataforma SaaS',
                'color_primario'   => '#d68643',
                'color_secundario' => '#a85f24',
            ]);
        });
    }

    public static function limpiarCache(): void
    {
        Cache::forget('config_plataforma_actual');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limpiarCache());
        static::deleted(fn () => self::limpiarCache());
    }
}
