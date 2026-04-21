<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'estado',
        'ciclo',
        'monto',
        'moneda',
        'fecha_inicio',
        'fecha_fin',
        'proxima_factura_at',
        'fecha_cancelacion',
        'motivo_cancelacion',
        'notas',
    ];

    protected $casts = [
        'monto'              => 'decimal:2',
        'fecha_inicio'       => 'date',
        'fecha_fin'          => 'date',
        'proxima_factura_at' => 'date',
        'fecha_cancelacion'  => 'date',
    ];

    public const ESTADO_ACTIVA     = 'activa';
    public const ESTADO_TRIAL      = 'en_trial';
    public const ESTADO_SUSPENDIDA = 'suspendida';
    public const ESTADO_CANCELADA  = 'cancelada';
    public const ESTADO_EXPIRADA   = 'expirada';

    public const CICLO_MENSUAL = 'mensual';
    public const CICLO_ANUAL   = 'anual';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function scopeActivas($q)
    {
        return $q->whereIn('estado', [self::ESTADO_ACTIVA, self::ESTADO_TRIAL]);
    }

    public function estaVencida(): bool
    {
        return $this->fecha_fin && $this->fecha_fin->isPast();
    }

    public function diasParaVencer(): ?int
    {
        if (!$this->fecha_fin) return null;
        return (int) now()->startOfDay()->diffInDays($this->fecha_fin->startOfDay(), false);
    }

    public function totalPagado(): float
    {
        return (float) $this->pagos()->where('estado', 'confirmado')->sum('monto');
    }
}
