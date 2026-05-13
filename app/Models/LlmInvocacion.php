<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmInvocacion extends Model
{
    protected $table = 'llm_invocaciones';

    protected $fillable = [
        'tenant_id', 'conversacion_id', 'telefono',
        'provider', 'modelo', 'es_fallback',
        'http_status', 'exitoso', 'error_tipo', 'error_mensaje',
        'tokens_input', 'tokens_output', 'tokens_cache_read', 'tokens_cache_creation',
        'latencia_ms', 'intentos', 'messages_count', 'tools_count',
    ];

    protected $casts = [
        'es_fallback' => 'boolean',
        'exitoso'     => 'boolean',
    ];

    /** Helper: clasifica el estado a un string corto para la UI */
    public function getEstadoCortoAttribute(): string
    {
        if ($this->exitoso) return 'ok';
        return match (true) {
            $this->http_status === 429 => 'rate_limit',
            $this->http_status === 529 => 'overloaded',
            $this->http_status === 400 => 'invalid_request',
            $this->http_status === 401 || $this->http_status === 403 => 'auth',
            $this->http_status >= 500  => 'server_error',
            default => 'error',
        };
    }

    /** Total de tokens efectivos (input + output, ignorando cache read) */
    public function getTokensTotalAttribute(): int
    {
        return (int) ($this->tokens_input ?? 0) + (int) ($this->tokens_output ?? 0);
    }
}
