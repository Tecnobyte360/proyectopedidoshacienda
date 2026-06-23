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
        'igsid',
        'psid',
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
        'fijada_at',
        'marcada_no_leida',
    ];

    // (los campos de reacción/respondiendo_a viven en mensajes_whatsapp, no acá)

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
        'fijada_at'           => 'datetime',
        'marcada_no_leida'    => 'boolean',
    ];

    public const ESTADO_ACTIVA     = 'activa';
    public const ESTADO_CERRADA    = 'cerrada';
    public const ESTADO_ARCHIVADA  = 'archivada';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Teléfono a mostrar en la UI. Para el chat web (canal 'widget') el
     * telefono_normalizado es un código interno (w....); en ese caso usamos
     * el celular real que el visitante dejó en el formulario (cliente->telefono).
     */
    public function getTelefonoVisibleAttribute(): string
    {
        if (($this->canal ?? '') === 'widget') {
            $real = trim((string) ($this->cliente->telefono ?? ''));
            if ($real !== '' && !str_starts_with($real, 'w')) {
                return $real;
            }
            return 'Cliente web (sin tel.)';
        }
        return (string) $this->telefono_normalizado;
    }

    /** Solo dígitos del teléfono real, para armar enlaces wa.me / tel:. */
    public function getTelefonoDigitosAttribute(): string
    {
        $base = ($this->canal ?? '') === 'widget'
            ? (string) ($this->cliente->telefono ?? '')
            : (string) $this->telefono_normalizado;
        return preg_replace('/[^0-9]/', '', $base) ?: '';
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
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
        // 🧠 Configurable desde ConfiguracionBot
        try {
            $cfg = ConfiguracionBot::actual();
            if ($cfg) {
                $cantidad  = (int) ($cfg->memoria_msgs_max ?? 50);
                $maxBloque = (int) ($cfg->memoria_chars_max ?? 80000);
            } else {
                $maxBloque = 80000;
            }
        } catch (\Throwable $e) {
            $maxBloque = 80000;
        }
        $maxMsg = 3000; // cada mensaje truncado a 3k chars

        // Clamp para evitar valores extremos
        $cantidad  = max(10, min(200, $cantidad));
        $maxBloque = max(10000, min(300000, $maxBloque));

        $query = MensajeWhatsapp::query()
            ->where('conversacion_id', $this->id)
            ->whereIn('rol', ['user', 'assistant']);

        // 🛡️ Si está activo el aislamiento por día, filtrar a HOY (Bogotá).
        // 🐛 FIX: MySQL almacena timestamps en hora local (Bogotá según config Laravel).
        // No convertir a UTC, comparar en la misma TZ del storage.
        try {
            $cfg = ConfiguracionBot::actual();
            if ($cfg && (bool) ($cfg->aislar_contexto_por_dia ?? true)) {
                $inicioHoyBogota = \Carbon\Carbon::now('America/Bogota')->startOfDay();
                $query->where('created_at', '>=', $inicioHoyBogota);
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
        $contenidoAnterior = null;  // para deduplicar mensajes idénticos consecutivos
        $rolAnterior = null;

        foreach ($mensajes as $m) {
            $contenido = trim((string) $m->contenido);

            // 🧹 FILTRO 1: descartar mensajes vacíos o demasiado cortos sin sentido
            // (no aplica a respuestas naturales como "sí", "ok", "dale")
            $longitudPura = mb_strlen($contenido);
            if ($longitudPura === 0) continue;

            // 🧹 FILTRO 2: descartar basura tipo "ererererer", "fffffff", "aaaaa",
            // "ababab", "asdfasdf". Heurística: si el mensaje tiene ≥5 chars y
            // solo 1-2 caracteres distintos (sin espacios), es tecleo de prueba.
            if ($longitudPura >= 5 && !preg_match('/\s/u', $contenido)) {
                $unicos = count(array_unique(mb_str_split(mb_strtolower($contenido))));
                if ($unicos <= 2) {
                    continue;
                }
            }

            // 🧹 FILTRO 3: dedupe consecutivo — mismo rol + mismo contenido seguido
            // (ej: 5 veces el mismo mensaje de "Caminata Canina" del bot)
            if ($m->rol === $rolAnterior && $contenido === $contenidoAnterior) {
                continue;
            }

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
            $contenidoAnterior = $contenido;
            $rolAnterior = $m->rol;
        }

        return $resultado;
    }
}
