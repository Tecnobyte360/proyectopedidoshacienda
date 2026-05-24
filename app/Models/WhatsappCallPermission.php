<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsappCallPermission extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_call_permissions';

    public const PENDING  = 'pending';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const EXPIRED  = 'expired';

    protected $fillable = [
        'tenant_id', 'telefono', 'estado',
        'solicitado_at', 'respondido_at', 'expira_at',
        'payload',
    ];

    protected $casts = [
        'payload'       => 'array',
        'solicitado_at' => 'datetime',
        'respondido_at' => 'datetime',
        'expira_at'     => 'datetime',
    ];

    /** ¿El permiso está vigente para llamar ahora? */
    public function vigente(): bool
    {
        if ($this->estado !== self::ACCEPTED) return false;
        if ($this->expira_at && $this->expira_at->isPast()) return false;
        return true;
    }
}
