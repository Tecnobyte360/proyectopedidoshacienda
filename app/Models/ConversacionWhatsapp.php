<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversacionWhatsapp extends Model
{
    use SoftDeletes, \App\Models\Concerns\BelongsToTenant;

    protected $table = 'conversaciones_whatsapp';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'telefono_normalizado',
        'canal',
        'sede_id',
        'connection_id',
        'estado',
        'atendida_por_humano',
        'es_interna',
        'departamento_id',
        'derivada_at',
        'no_leidos',
        'ultima_vista_at',
        'total_mensajes',
        'total_mensajes_cliente',
        'total_mensajes_bot',
        'genero_pedido',
        'pedido_id',
        'primer_mensaje_at',
        'ultimo_mensaje_at',
        'requiere_humano',
        'humano_motivo',
        'humano_solicitado_at',
        'humano_atendido_at',
    ];

    protected $casts = [
        'atendida_por_humano' => 'boolean',
        'requiere_humano'     => 'boolean',
        'humano_solicitado_at'=> 'datetime',
        'humano_atendido_at'  => 'datetime',
        'es_interna'          => 'boolean',
        'derivada_at'         => 'datetime',
        'ultima_vista_at'     => 'datetime',
        'no_leidos'           => 'integer',
        'genero_pedido'       => 'boolean',
        'primer_mensaje_at'   => 'datetime',
        'ultimo_mensaje_at'   => 'datetime',
    ];

    public const ESTADO_ACTIVA     = 'activa';
    public const ESTADO_CERRADA    = 'cerrada';
    public const ESTADO_ARCHIVADA  = 'archivada';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function mensajes(): HasMany
    {
        // Orden cronológico estable: created_at + id como tiebreaker
        // (varios mensajes en el mismo segundo son comunes en chat)
        return $this->hasMany(MensajeWhatsapp::class, 'conversacion_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function ultimosMensajes(int $cantidad = 20): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class, 'conversacion_id')
            ->whereIn('rol', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($cantidad);
    }

    public function scopeActivas($q)
    {
        return $q->where('estado', self::ESTADO_ACTIVA);
    }

    public function scopeRecientes($q)
    {
        return $q->orderByDesc('ultimo_mensaje_at');
    }

    /**
     * Devuelve los últimos N mensajes en orden cronológico (user/assistant)
     * formateados para inyectar al prompt de OpenAI.
     *
     * 🛡️ AISLAMIENTO POR DÍA: solo se envían a la IA mensajes de HOY
     * (zona horaria America/Bogota). Mensajes de días anteriores quedan en BD
     * intactos para auditoría / reclamos / análisis, pero NO contaminan el
     * contexto del bot. Así el bot no se confunde con pedidos viejos.
     *
     * IMPORTANTE: trunca cada mensaje a 2000 chars y todo el bloque a 30k chars
     * MAX para evitar requests gigantes a OpenAI (rate_limit_exceeded).
     */
    public function historialParaIA(int $cantidad = 50): array
    {
        $maxBloque = 80000; // 80k chars total max (~20k tokens)
        $maxMsg    = 3000;  // cada mensaje truncado a 3k chars

        $query = MensajeWhatsapp::query()
            ->where('conversacion_id', $this->id)
            ->whereIn('rol', ['user', 'assistant']);

        // 🛡️ Si está activo el aislamiento por día, filtrar a HOY (Bogotá)
        try {
            $cfg = ConfiguracionBot::actual();
            if ($cfg && (bool) ($cfg->aislar_contexto_por_dia ?? true)) {
                $inicioHoyUtc = \Carbon\Carbon::now('America/Bogota')->startOfDay()->utc();
                $query->where('created_at', '>=', $inicioHoyUtc);
            }
        } catch (\Throwable $e) {
            // En caso de error de config, fallback a comportamiento clásico
        }

        $mensajes = $query
            ->orderByDesc('id')
            ->limit($cantidad)
            ->get()
            ->reverse()
            ->values();

        $bytesAcumulados = 0;
        $resultado = [];

        foreach ($mensajes as $m) {
            $contenido = (string) $m->contenido;
            // Truncar mensajes individuales muy largos (ej: dumps de tool results)
            if (mb_strlen($contenido) > $maxMsg) {
                $contenido = mb_substr($contenido, 0, $maxMsg) . ' …[truncado]';
            }
            $bytesAcumulados += mb_strlen($contenido);
            // Si el total ya supera el max, paramos (mantenemos los más recientes)
            if ($bytesAcumulados > $maxBloque) break;
            $resultado[] = [
                'role'    => $m->rol,
                'content' => $contenido,
            ];
        }

        return $resultado;
    }
}
