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
    ];

    protected $hidden = [
        'whatsapp_admin_password',
    ];

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
