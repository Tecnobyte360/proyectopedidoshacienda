<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchdogRescate extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'watchdog_rescates';

    protected $fillable = [
        'tenant_id',
        'conversacion_id',
        'telefono',
        'mensaje_origen_id',
        'mensaje_contenido',
        'segundos_estancada',
        'exitoso',
        'error_mensaje',
        'resuelto_at',
        'resuelto_por_user_id',
    ];

    protected $casts = [
        'exitoso'            => 'boolean',
        'segundos_estancada' => 'integer',
        'resuelto_at'        => 'datetime',
    ];

    public function conversacion()
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }
}
