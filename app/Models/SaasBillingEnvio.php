<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingEnvio extends Model
{
    protected $table = 'saas_billing_envios';

    public const TIPO_FACTURA       = 'factura';
    public const TIPO_RECORDATORIO  = 'recordatorio';
    public const TIPO_SUSPENDIDO    = 'suspendido';

    public const TIPOS = [
        self::TIPO_FACTURA      => '🧾 Factura nueva',
        self::TIPO_RECORDATORIO => '⏰ Recordatorio',
        self::TIPO_SUSPENDIDO   => '🚫 Suspendido',
    ];

    public const ETAPAS = [
        'factura'      => '🧾 Factura nueva',
        'preaviso'     => '📅 Preaviso (-3d)',
        'vence_hoy'    => '⏰ Vence hoy',
        'vencio_ayer'  => '⚠️ Venció ayer',
        'urgencia'     => '🚨 Urgencia (+3d)',
        'suspendido'   => '🚫 Suspendido',
    ];

    protected $fillable = [
        'tenant_id', 'pago_id', 'suscripcion_id',
        'tipo', 'etapa', 'canal', 'telefono',
        'monto', 'moneda',
        'ok', 'mensaje', 'link_pago', 'error',
    ];

    protected $casts = [
        'ok'    => 'boolean',
        'monto' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function etapaLabel(): string
    {
        return self::ETAPAS[$this->etapa ?? ''] ?? '—';
    }
}
