<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración global del bot WhatsApp.
 * Singleton: siempre hay UNA fila (la de id=1).
 */
class ConfiguracionBot extends Model
{
    protected $table = 'configuraciones_bot';

    protected $fillable = [
        'enviar_imagenes_productos',
        'max_imagenes_por_mensaje',
        'enviar_imagen_destacados',
        'saludar_con_promociones',
        'modelo_openai',
        'temperatura',
        'max_tokens',
        'nombre_asesora',
        'frase_bienvenida',
        'activo',
    ];

    protected $casts = [
        'enviar_imagenes_productos' => 'boolean',
        'enviar_imagen_destacados'  => 'boolean',
        'saludar_con_promociones'   => 'boolean',
        'activo'                    => 'boolean',
        'temperatura'               => 'float',
        'max_tokens'                => 'integer',
        'max_imagenes_por_mensaje'  => 'integer',
    ];

    /**
     * Obtiene (cacheada) la única instancia. Si no existe, crea una con defaults.
     */
    public static function actual(): self
    {
        return Cache::remember('config_bot_actual', 60, function () {
            return self::firstOrCreate(['id' => 1], [
                'enviar_imagenes_productos' => false,
                'max_imagenes_por_mensaje'  => 3,
                'enviar_imagen_destacados'  => false,
                'saludar_con_promociones'   => true,
                'modelo_openai'             => 'gpt-4o-mini',
                'temperatura'               => 0.85,
                'max_tokens'                => 700,
                'nombre_asesora'            => 'Sofía',
                'activo'                    => true,
            ]);
        });
    }

    public static function limpiarCache(): void
    {
        Cache::forget('config_bot_actual');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limpiarCache());
        static::deleted(fn () => self::limpiarCache());
    }
}
