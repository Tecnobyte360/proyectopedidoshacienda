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
        'derivacion_activa',
        'derivacion_instrucciones_ia',
        'derivacion_fallback_activa',
        'derivacion_frases_deteccion',
        'derivacion_departamento_fallback_id',
        'agrupar_mensajes_activo',
        'agrupar_mensajes_segundos',
        'modelo_openai',
        'ai_provider',
        'modelo_anthropic',
        'temperatura',
        'max_tokens',
        'nombre_asesora',
        'frase_bienvenida',
        'info_empresa',
        'usar_prompt_personalizado',
        'system_prompt',
        'instrucciones_extra',
        'bot_zonas_ids',
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
        'fuente_productos',
        'integracion_productos_id',
        'auto_sync_productos_min',
        'ultimo_sync_productos_at',
        'categorias_excluidas_bot',
        'excluir_productos_sin_precio',
        'bot_modo_agente',
        'pedir_cedula',
        'cedula_obligatoria',
        'cedula_descripcion',
        'cedula_consulta_id',
        'pedir_correo',
        'cumpleanos_dias_vigencia_beneficio',
        'encuesta_activa',
        'encuesta_delay_minutos',
        'encuesta_mensaje',
        'enviar_link_pago',
        'aceptar_pedidos_fuera_horario',
        'flujo_pedido_orden',
        'auto_limpieza_activa',
        'auto_limpieza_hora',
        'auto_limpieza_dias',
        'auto_limpieza_max_msgs',
        'watchdog_activo',
        'watchdog_min_segundos',
        'watchdog_max_minutos',
        'watchdog_skip_pedido_min',
        'watchdog_cooldown_conv_min',
        'auto_reset_horas_inactividad',
        'aislar_contexto_por_dia',
        'auto_asignar_domiciliario',
        'criterio_asignacion',
        'asignar_en_estado',
        'notif_en_preparacion_activa',
        'notif_en_camino_activa',
        'notif_entregado_activa',
        'notif_pago_aprobado_activa',
        'notif_pago_rechazado_activa',
        'notif_pedido_confirmado_mensaje',
        'notif_en_preparacion_mensaje',
        'notif_en_camino_mensaje',
        'notif_entregado_mensaje',
        'notif_pago_aprobado_mensaje',
        'notif_pago_rechazado_mensaje',
        'notif_en_preparacion_delay',
        'notif_en_camino_delay',
        'notif_entregado_delay',
        'notif_pago_aprobado_delay',
        'notif_pago_rechazado_delay',
        'cola_salida_activa',
        'cola_salida_max_intentos',
        'cola_salida_backoff_segundos',
        'cola_salida_email_alerta',
        'pedido_max_auto',
        'memoria_msgs_max',
        'memoria_chars_max',
    ];

    /** Plantillas por defecto si el tenant no las personaliza. */
    public const NOTIF_DEFAULTS = [
        'pedido_confirmado' => "✨ ¡Pedido confirmado, {nombre}!\n\n🧾 *Pedido #{pedido}*\n{productos}\n\n📍 *Dirección:* {direccion}\n🏘️ *Barrio:* {barrio}\n☎️ *Contacto:* {telefono_contacto}\n{beneficio}💰 *Total:* {total}\n{bloque_pago}\n🔗 Seguir tu pedido aquí:\n{link_seguimiento}\n\nGuarda *#{pedido}* para futuras consultas. 🙌",
        'en_preparacion'  => "🍳 {nombre}, ya estamos preparando tu pedido.\nTe aviso apenas salga para tu casa.",
        'en_camino'       => "🛵 {nombre}, tu pedido va en camino.\n\nCuando llegue el domiciliario, dile este código para confirmar la entrega:\n\n🔐 *{token}*\n\n¡Ya casi llega! 🙌",
        'pickup_listo'    => "📦 {nombre}, tu pedido #{pedido} ya está listo para que pases a recogerlo.\n\nCuando llegues a la sede, dile al cajero este código para confirmar la entrega:\n\n🔐 *{token}*\n\n¡Te esperamos! 🙌",
        'en_preparacion_pickup' => "🍳 {nombre}, ya estamos preparando tu pedido #{pedido}.\nTe aviso apenas esté listo para que pases a recogerlo. 🙌",
        'entregado'       => "✅ Listo {nombre}.\n\nTu pedido fue entregado. ¡Gracias por confiar en nosotros!\n\nEn un momento te paso una encuesta cortica para saber cómo estuvo todo. 🌟",
        'pago_aprobado'   => "💳 {nombre}, recibimos tu pago de {total}.\n\nTu pedido *#{pedido}* ya quedó pagado. Procedemos a prepararlo y te avisamos cuando salga. 🛵",
        'pago_rechazado'  => "⚠️ Hola {nombre}, tu pago no se pudo procesar.\n\nTu pedido *#{pedido}* sigue activo. Puedes intentar de nuevo aquí:\n🔗 {link_pago}\n\nO escríbenos si prefieres pagar contra entrega. 🙌",
    ];

    protected $casts = [
        'enviar_imagenes_productos' => 'boolean',
        'transcribir_audios'        => 'boolean',
        'enviar_imagen_destacados'  => 'boolean',
        'saludar_con_promociones'   => 'boolean',
        'derivacion_activa'         => 'boolean',
        'derivacion_fallback_activa'=> 'boolean',
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
        'bot_zonas_ids'             => 'array',
        'encuesta_activa'           => 'boolean',
        'encuesta_delay_minutos'    => 'integer',
        'enviar_link_pago'          => 'boolean',
        'aceptar_pedidos_fuera_horario' => 'boolean',
        'flujo_pedido_orden'        => 'array',
        'auto_limpieza_activa'      => 'boolean',
        'auto_limpieza_dias'        => 'integer',
        'watchdog_activo'           => 'boolean',
        'watchdog_min_segundos'     => 'integer',
        'watchdog_max_minutos'      => 'integer',
        'watchdog_skip_pedido_min'  => 'integer',
        'watchdog_cooldown_conv_min'=> 'integer',
        'cola_salida_activa'          => 'boolean',
        'cola_salida_max_intentos'    => 'integer',
        'cola_salida_backoff_segundos'=> 'array',
        'pedido_max_auto'             => 'integer',
        'memoria_msgs_max'            => 'integer',
        'memoria_chars_max'           => 'integer',
        'auto_limpieza_max_msgs'    => 'integer',
        'auto_reset_horas_inactividad' => 'integer',
        'aislar_contexto_por_dia'   => 'boolean',
        'auto_asignar_domiciliario' => 'boolean',
        'notif_en_preparacion_activa' => 'boolean',
        'notif_en_camino_activa'      => 'boolean',
        'notif_entregado_activa'      => 'boolean',
        'notif_pago_aprobado_activa'  => 'boolean',
        'notif_pago_rechazado_activa' => 'boolean',
        'auto_sync_productos_min'   => 'integer',
        'ultimo_sync_productos_at'  => 'datetime',
        'categorias_excluidas_bot'  => 'array',
        'excluir_productos_sin_precio' => 'boolean',
        'bot_modo_agente'           => 'boolean',
        'pedir_cedula'              => 'boolean',
        'cedula_obligatoria'        => 'boolean',
        'pedir_correo'              => 'boolean',
    ];

    public const FUENTE_TABLA       = 'tabla';
    public const FUENTE_INTEGRACION = 'integracion';

    public function integracionProductos()
    {
        return $this->belongsTo(\App\Models\Integracion::class, 'integracion_productos_id');
    }

    /** Instrucciones IA por defecto para derivación automática a departamento */
    public const DERIVACION_INSTRUCCIONES_DEFAULT = <<<'TXT'
⚠️ REGLA DE ORO — EJECUTA ESTA FUNCIÓN, NO HABLES DE ELLA:

❌ PROHIBIDO decir en texto al cliente:
  - "voy a derivar" / "déjame un momento" / "te paso con..."
  - "contacta directamente a [departamento]"
  - "te recomiendo que te comuniques con..."
  - "ese tema no lo manejo yo" (sin llamar la función)
  - Cualquier forma de mandar al cliente a buscar ayuda por su cuenta.

✅ EN VEZ DE ESO: LLAMA esta función. El sistema conecta automáticamente
al cliente con el departamento correcto y responde por ti con el saludo
personalizado. Tú NUNCA redactas el mensaje de derivación — lo hace el sistema.

════════════════════════════════════════════════════════════════════════
🚨 REGLA CRÍTICA: SOLO DERIVA EN CASOS REALES — NO POR PREGUNTAS COMUNES
════════════════════════════════════════════════════════════════════════

❌ **NO DERIVES NUNCA** por estas situaciones (responde TÚ con el catálogo):

• Cliente pregunta por una variante/preparación del producto:
  - "¿me hacen las patitas al chicharrón?" → Responde: "no manejamos
     ese corte/preparación, te lo entrego como aparece en el catálogo"
  - "¿lo aliñan?", "¿me lo pican?", "¿con o sin hueso?" → mira el catálogo,
     si tienes cortes/opciones úsalas; si no, di que no manejas eso.

• Cliente pregunta si tienen algo que NO está en catálogo:
  - "¿tienen huevos?" / "¿tienen leche?" → Responde: "no manejamos eso,
     pero tengo [alternativa del catálogo]". NO derives, RESPONDE TÚ.

• Cliente quiere agregar más productos al pedido en curso:
  - "quiero también hueso para sancocho" → BUSCA en catálogo y agrega.
  - "agrega también pollo" → llama agregar_producto_al_pedido.

• Preguntas básicas sobre precio, peso, presentación → catálogo.

────────────────────────────────────────────────────────────────────────

CUÁNDO SÍ derivar (casos GENUINOS):

• TONO NEGATIVO REAL: enojo evidente, frustración severa, sarcasmo agresivo.
• EXPRESIONES DE MOLESTIA FUERTE: amenazas explícitas ("voy a demandar",
  "voy a reportar a la SIC", "nunca más vuelvo a comprar").
• PROBLEMA CON PEDIDO ANTERIOR (ENTREGADO): inconformidad con algo ya
  recibido — NO con algo en curso.
• PIDE HABLAR EXPLÍCITAMENTE CON HUMANO: "pásame con alguien",
  "quiero hablar con una persona", "no me están entendiendo", "asesor".
• TEMA FUERA DEL FLUJO DE VENTAS:
  - Hoja de vida / empleo / vacantes → RH.
  - Facturación empresarial / NIT / retenciones → Facturación.
  - Cotizaciones mayoristas / B2B / convenios corporativos → Comercial.
  - Reclamo formal / PQR / devolución de dinero → Servicio al Cliente.
• INSISTENCIA: cuarta vez que pregunta exactamente lo mismo SIN resolver.

────────────────────────────────────────────────────────────────────────

⚠️ ANTES DE DERIVAR, PREGÚNTATE:
1. ¿Está armando un pedido normal? → NO derives, sigue ayudando.
2. ¿Es una pregunta sobre catálogo/producto? → NO derives, responde.
3. ¿Pidió explícitamente hablar con humano? → SÍ deriva.
4. ¿Está enojado de verdad o frustrado? → SÍ deriva.

REGLA: en caso de duda, **NO derives**. Es preferible que el bot intente
ayudar (puede equivocarse) a derivar prematuramente (pierde ventas).
TXT;

    /** Frases que, si aparecen en la respuesta del bot SIN tool_call, indican "dijo pero no hizo" derivación */
    public const DERIVACION_FRASES_DEFAULT = "voy a derivar,voy a transferir,voy a pasarte,paso con,te voy a conectar,te conecto con,voy a conectarte,déjame un momento para derivar,déjame un momento para pasar,te transfiero,un asesor te va a contactar,te va a llamar un asesor";

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
                'ai_provider'               => 'openai',
                'modelo_anthropic'          => 'claude-sonnet-4-6',
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
