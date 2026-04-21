<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'tenant_id',
        'suscripcion_id',
        'monto',
        'moneda',
        'metodo',
        'referencia',
        'comprobante_url',
        'fecha_pago',
        'cubre_desde',
        'cubre_hasta',
        'estado',
        'notas',
        'registrado_por',
    ];

    protected $casts = [
        'monto'       => 'decimal:2',
        'fecha_pago'  => 'date',
        'cubre_desde' => 'date',
        'cubre_hasta' => 'date',
    ];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_RECHAZADO  = 'rechazado';

    public const METODO_EFECTIVO       = 'efectivo';
    public const METODO_TRANSFERENCIA  = 'transferencia';
    public const METODO_NEQUI          = 'nequi';
    public const METODO_DAVIPLATA      = 'daviplata';
    public const METODO_TARJETA        = 'tarjeta';
    public const METODO_OTRO           = 'otro';

    public const METODOS = [
        self::METODO_EFECTIVO       => 'Efectivo',
        self::METODO_TRANSFERENCIA  => 'Transferencia bancaria',
        self::METODO_NEQUI          => 'Nequi',
        self::METODO_DAVIPLATA      => 'Daviplata',
        self::METODO_TARJETA        => 'Tarjeta',
        self::METODO_OTRO           => 'Otro',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function metodoLabel(): string
    {
        return self::METODOS[$this->metodo] ?? ucfirst($this->metodo);
    }
}
