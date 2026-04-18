<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelicitacionCumpleanos extends Model
{
    protected $table = 'felicitaciones_cumpleanos';

    protected $fillable = [
        'cliente_id',
        'cliente_nombre',
        'telefono',
        'estado',
        'mensaje',
        'error_detalle',
        'origen',
        'anio',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'anio'       => 'integer',
    ];

    public const ESTADO_ENVIADO = 'enviado';
    public const ESTADO_FALLIDO = 'fallido';
    public const ESTADO_DRY_RUN = 'dry_run';

    public const ORIGEN_SCHEDULED = 'scheduled';
    public const ORIGEN_MANUAL    = 'manual';
    public const ORIGEN_FORCE     = 'force';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function badgeColor(): string
    {
        return match ($this->estado) {
            self::ESTADO_ENVIADO => 'emerald',
            self::ESTADO_FALLIDO => 'rose',
            self::ESTADO_DRY_RUN => 'slate',
            default              => 'slate',
        };
    }

    public function badgeIcono(): string
    {
        return match ($this->estado) {
            self::ESTADO_ENVIADO => '✅',
            self::ESTADO_FALLIDO => '❌',
            self::ESTADO_DRY_RUN => '👁️',
            default              => '•',
        };
    }
}
