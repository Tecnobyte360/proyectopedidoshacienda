<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgenteToolInvocacion extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'agente_tool_invocaciones';

    protected $fillable = [
        'tenant_id',
        'conversacion_id',
        'tool_name',
        'connection_id',
        'telefono_cliente',
        'args',
        'resultado',
        'count_resultados',
        'exitoso',
        'error',
        'latencia_ms',
        'tokens_estimados',
    ];

    protected $casts = [
        'args'             => 'array',
        'resultado'        => 'array',
        'count_resultados' => 'integer',
        'exitoso'          => 'boolean',
        'latencia_ms'      => 'integer',
        'tokens_estimados' => 'integer',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }
}
