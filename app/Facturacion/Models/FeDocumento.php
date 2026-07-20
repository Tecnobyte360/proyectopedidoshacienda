<?php

namespace App\Facturacion\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento electrónico y su ciclo de vida ante la DIAN.
 */
class FeDocumento extends Model
{
    protected $table = 'fe_documentos';
    protected $guarded = ['id'];

    protected $casts = [
        'dian_errores'     => 'array',
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'total'            => 'decimal:2',
        'sent_at'          => 'datetime',
        'validated_at'     => 'datetime',
    ];

    // Estados del ciclo de vida.
    public const DRAFT       = 'draft';
    public const PENDING     = 'pending';
    public const SENDING     = 'sending';
    public const ACCEPTED    = 'accepted';
    public const REJECTED    = 'rejected';
    public const CONTINGENCY = 'contingency';
    public const CANCELLED   = 'cancelled';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function resolucion(): BelongsTo
    {
        return $this->belongsTo(FeResolucion::class, 'fe_resolucion_id');
    }

    public function aceptado(): bool
    {
        return $this->estado === self::ACCEPTED;
    }

    /** Un documento aceptado NO se edita ni se borra: se corrige con nota. */
    public function esEditable(): bool
    {
        return in_array($this->estado, [self::DRAFT, self::REJECTED], true);
    }
}
