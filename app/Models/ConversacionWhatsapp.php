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
        'total_mensajes',
        'total_mensajes_cliente',
        'total_mensajes_bot',
        'genero_pedido',
        'pedido_id',
        'primer_mensaje_at',
        'ultimo_mensaje_at',
    ];

    protected $casts = [
        'atendida_por_humano' => 'boolean',
        'es_interna'          => 'boolean',
        'derivada_at'         => 'datetime',
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
     */
    public function historialParaIA(int $cantidad = 20): array
    {
        // Tomamos los últimos N por id desc (estable) y luego volteamos a cronológico
        return MensajeWhatsapp::query()
            ->where('conversacion_id', $this->id)
            ->whereIn('rol', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($cantidad)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => [
                'role'    => $m->rol,
                'content' => (string) $m->contenido,
            ])
            ->toArray();
    }
}
