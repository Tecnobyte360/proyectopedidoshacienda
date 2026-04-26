<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EncuestaPedido extends Model
{
    use BelongsToTenant;

    protected $table = 'encuestas_pedido';

    protected $fillable = [
        'tenant_id',
        'pedido_id',
        'domiciliario_id',
        'token',
        'calificacion_proceso',
        'calificacion_domiciliario',
        'comentario_proceso',
        'comentario_domiciliario',
        'recomendaria',
        'enviada_at',
        'completada_at',
        'vista_at',
    ];

    protected $casts = [
        'recomendaria'              => 'boolean',
        'calificacion_proceso'      => 'integer',
        'calificacion_domiciliario' => 'integer',
        'enviada_at'                => 'datetime',
        'completada_at'             => 'datetime',
        'vista_at'                  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($encuesta) {
            if (empty($encuesta->token)) {
                $encuesta->token = (string) Str::uuid();
            }
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function domiciliario(): BelongsTo
    {
        return $this->belongsTo(Domiciliario::class);
    }

    public function isCompletada(): bool
    {
        return $this->completada_at !== null;
    }

    public function urlPublica(): string
    {
        return url("/encuesta/{$this->token}");
    }
}
