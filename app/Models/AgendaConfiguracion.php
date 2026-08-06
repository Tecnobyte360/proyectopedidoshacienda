<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de agenda de un tenant (horarios + Google Calendar).
 */
class AgendaConfiguracion extends Model
{
    use BelongsToTenant;

    protected $table = 'agenda_configuraciones';
    protected $guarded = ['id'];

    protected $hidden = ['google_access_token', 'google_refresh_token'];

    protected $casts = [
        'dias'                  => 'array',
        'activa'                => 'boolean',
        'google_conectado'      => 'boolean',
        'google_access_token'   => 'encrypted',
        'google_refresh_token'  => 'encrypted',
        'google_token_expira_at' => 'datetime',
        'duracion_min'          => 'integer',
        'buffer_min'            => 'integer',
    ];

    /** Devuelve (o crea con defaults) la config del tenant actual. */
    public static function paraTenantActual(): self
    {
        $tid = app(\App\Services\TenantManager::class)->id();
        return static::firstOrCreate(
            ['tenant_id' => $tid],
            ['dias' => [1, 2, 3, 4, 5], 'hora_inicio' => '08:00', 'hora_fin' => '18:00']
        );
    }
}
