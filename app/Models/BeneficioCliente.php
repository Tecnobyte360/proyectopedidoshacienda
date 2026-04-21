<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeneficioCliente extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'beneficios_clientes';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'felicitacion_id',
        'tipo',
        'valor',
        'origen',
        'descripcion',
        'otorgado_at',
        'vigente_hasta',
        'usado_at',
        'pedido_id',
    ];

    protected $casts = [
        'otorgado_at'   => 'datetime',
        'vigente_hasta' => 'date',
        'usado_at'      => 'datetime',
        'valor'         => 'decimal:2',
    ];

    public const TIPO_ENVIO_GRATIS    = 'envio_gratis';
    public const TIPO_DESCUENTO_PCT   = 'descuento_pct';
    public const TIPO_DESCUENTO_MONTO = 'descuento_monto';

    public const ORIGEN_CUMPLEANOS = 'cumpleanos';
    public const ORIGEN_MANUAL     = 'manual';
    public const ORIGEN_PROMO      = 'promo';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function felicitacion(): BelongsTo
    {
        return $this->belongsTo(FelicitacionCumpleanos::class, 'felicitacion_id');
    }

    public function scopeVigentes($q)
    {
        return $q->whereNull('usado_at')
            ->where('vigente_hasta', '>=', now()->toDateString());
    }

    public function scopeUsados($q)
    {
        return $q->whereNotNull('usado_at');
    }

    public function scopeExpirados($q)
    {
        return $q->whereNull('usado_at')
            ->where('vigente_hasta', '<', now()->toDateString());
    }

    public function estaVigente(): bool
    {
        return $this->usado_at === null
            && $this->vigente_hasta
            && $this->vigente_hasta->gte(now()->startOfDay());
    }

    public function estado(): string
    {
        if ($this->usado_at) return 'usado';
        if (!$this->estaVigente()) return 'expirado';
        return 'vigente';
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_ENVIO_GRATIS    => '🚚 Envío gratis',
            self::TIPO_DESCUENTO_PCT   => '% Descuento ' . ($this->valor ?? 0) . '%',
            self::TIPO_DESCUENTO_MONTO => '💵 Descuento $' . number_format($this->valor ?? 0, 0, ',', '.'),
            default                     => $this->tipo,
        };
    }
}
