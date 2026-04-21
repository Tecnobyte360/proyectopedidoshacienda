<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotAlerta extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'bot_alertas';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'severidad',
        'titulo',
        'mensaje',
        'contexto',
        'codigo_http',
        'hash_dedup',
        'ocurrencias',
        'resuelta',
        'resuelta_at',
        'resuelta_por',
        'vista_at',
        'ultima_ocurrencia_at',
    ];

    protected $casts = [
        'contexto'             => 'array',
        'resuelta'             => 'boolean',
        'resuelta_at'          => 'datetime',
        'vista_at'             => 'datetime',
        'ultima_ocurrencia_at' => 'datetime',
        'ocurrencias'          => 'integer',
        'codigo_http'          => 'integer',
    ];

    public const SEV_CRITICA = 'critica';
    public const SEV_WARNING = 'warning';
    public const SEV_INFO    = 'info';

    public const TIPO_OPENAI_CREDITO    = 'openai_credito';
    public const TIPO_OPENAI_KEY        = 'openai_key';
    public const TIPO_OPENAI_RATE       = 'openai_rate';
    public const TIPO_OPENAI_MODELO     = 'openai_modelo';
    public const TIPO_OPENAI_TIMEOUT    = 'openai_timeout';
    public const TIPO_OPENAI_OTRO       = 'openai_otro';
    public const TIPO_WHATSAPP_TOKEN    = 'whatsapp_token';
    public const TIPO_WHATSAPP_ENVIO    = 'whatsapp_envio';
    public const TIPO_REVERB            = 'reverb';
    public const TIPO_OTRO              = 'otro';

    public function scopeNoResueltas($q)
    {
        return $q->where('resuelta', false);
    }

    public function scopeRecientes($q)
    {
        return $q->orderByDesc('ultima_ocurrencia_at')->orderByDesc('created_at');
    }

    public function icono(): string
    {
        return match ($this->tipo) {
            self::TIPO_OPENAI_CREDITO => '💸',
            self::TIPO_OPENAI_KEY     => '🔑',
            self::TIPO_OPENAI_RATE    => '⏱️',
            self::TIPO_OPENAI_MODELO  => '🧠',
            self::TIPO_OPENAI_TIMEOUT => '⌛',
            self::TIPO_OPENAI_OTRO    => '🤖',
            self::TIPO_WHATSAPP_TOKEN => '📱',
            self::TIPO_WHATSAPP_ENVIO => '📤',
            self::TIPO_REVERB         => '🔌',
            default                   => '⚠️',
        };
    }

    public function colorSeveridad(): string
    {
        return match ($this->severidad) {
            self::SEV_CRITICA => 'rose',
            self::SEV_WARNING => 'amber',
            self::SEV_INFO    => 'blue',
            default           => 'slate',
        };
    }
}
