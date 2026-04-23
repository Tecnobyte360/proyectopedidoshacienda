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
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'configuraciones_bot';

    protected $fillable = [
        'tenant_id',
        'enviar_imagenes_productos',
        'transcribir_audios',
        'max_imagenes_por_mensaje',
        'enviar_imagen_destacados',
        'saludar_con_promociones',
        'agrupar_mensajes_activo',
        'agrupar_mensajes_segundos',
        'modelo_openai',
        'temperatura',
        'max_tokens',
        'nombre_asesora',
        'frase_bienvenida',
        'info_empresa',
        'usar_prompt_personalizado',
        'system_prompt',
        'activo',
        'cumpleanos_activo',
        'cumpleanos_hora',
        'cumpleanos_mensaje',
        'cumpleanos_dias_anticipacion',
        'cumpleanos_reintentos_max',
        'cumpleanos_ventana_desde',
        'cumpleanos_ventana_hasta',
        'cumpleanos_dias_semana',
        'connection_id_default',
        'cumpleanos_dias_vigencia_beneficio',
    ];

    protected $casts = [
        'enviar_imagenes_productos' => 'boolean',
        'transcribir_audios'        => 'boolean',
        'enviar_imagen_destacados'  => 'boolean',
        'saludar_con_promociones'   => 'boolean',
        'agrupar_mensajes_activo'   => 'boolean',
        'agrupar_mensajes_segundos' => 'integer',
        'usar_prompt_personalizado' => 'boolean',
        'activo'                    => 'boolean',
        'cumpleanos_activo'            => 'boolean',
        'cumpleanos_dias_anticipacion' => 'integer',
        'cumpleanos_reintentos_max'    => 'integer',
        'temperatura'                  => 'float',
        'max_tokens'                => 'integer',
        'max_imagenes_por_mensaje'  => 'integer',
    ];

    /** Plantilla por defecto del mensaje de cumpleaños */
    public const CUMPLEANOS_PLANTILLA_DEFAULT = <<<'MSG'
¡Feliz cumpleaños, {nombre}! 🎉🎂

De parte de todo el equipo de *Alimentos La Hacienda* queremos desearte un día increíble lleno de alegría y de cosas ricas 🥳.

Como regalito de cumpleaños, hoy tienes *envío gratis* en tu pedido 🎁🚚.
Solo escríbenos cuando quieras pedir y nosotros nos encargamos del resto.

¡Que la pases muy bonito! 🙌
MSG;

    /**
     * Obtiene la configuración del tenant actual (cacheada).
     * Si no existe, la crea con defaults.
     *
     * Multi-tenant: cada tenant tiene su propia configuración.
     */
    public static function actual(): self
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        $cacheKey = 'config_bot_actual_' . ($tenantId ?? 'global');

        return Cache::remember($cacheKey, 60, function () use ($tenantId) {
            $defaults = [
                'enviar_imagenes_productos' => false,
                'max_imagenes_por_mensaje'  => 3,
                'enviar_imagen_destacados'  => false,
                'saludar_con_promociones'   => true,
                'modelo_openai'             => 'gpt-4o-mini',
                'temperatura'               => 0.85,
                'max_tokens'                => 700,
                'nombre_asesora'            => 'Sofía',
                'activo'                    => true,
            ];

            // Si hay tenant, busca su config; si no, fallback a la primera (legacy)
            if ($tenantId) {
                return self::firstOrCreate(['tenant_id' => $tenantId], $defaults);
            }

            return self::first() ?? self::create($defaults);
        });
    }

    public static function limpiarCache(): void
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        Cache::forget('config_bot_actual_' . ($tenantId ?? 'global'));
        Cache::forget('config_bot_actual');   // legacy
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limpiarCache());
        static::deleted(fn () => self::limpiarCache());
    }
}
