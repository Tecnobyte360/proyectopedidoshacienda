<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsappBillingEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_billing_events';

    public const CAT_SERVICE        = 'service';
    public const CAT_UTILITY        = 'utility';
    public const CAT_MARKETING      = 'marketing';
    public const CAT_AUTHENTICATION = 'authentication';

    protected $fillable = [
        'tenant_id', 'conversation_id', 'message_id', 'telefono',
        'categoria', 'pricing_model', 'billable',
        'cost_usd', 'moneda', 'origin_type',
        'raw_payload', 'ocurrido_at',
    ];

    protected $casts = [
        'billable'    => 'boolean',
        'cost_usd'    => 'decimal:6',
        'raw_payload' => 'array',
        'ocurrido_at' => 'datetime',
    ];
}
