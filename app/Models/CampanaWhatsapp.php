<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampanaWhatsapp extends Model
{
    use BelongsToTenant;

    protected $table = 'campanas_whatsapp';

    protected $fillable = [
        'tenant_id', 'nombre', 'mensaje', 'media_url',
        'audiencia_tipo', 'audiencia_filtros',
        'intervalo_min_seg', 'intervalo_max_seg',
        'lote_tamano', 'descanso_lote_min',
        'ventana_desde', 'ventana_hasta',
        'estado', 'programada_para', 'iniciada_at', 'completada_at',
        'connection_id', 'creado_por',
        'total_destinatarios', 'total_enviados', 'total_fallidos', 'total_pendientes',
        'notas',
    ];

    protected $casts = [
        'audiencia_filtros'  => 'array',
        'programada_para'    => 'datetime',
        'iniciada_at'        => 'datetime',
        'completada_at'      => 'datetime',
        'intervalo_min_seg'  => 'integer',
        'intervalo_max_seg'  => 'integer',
        'lote_tamano'        => 'integer',
        'descanso_lote_min'  => 'integer',
        'connection_id'      => 'integer',
        'total_destinatarios'=> 'integer',
        'total_enviados'     => 'integer',
        'total_fallidos'     => 'integer',
        'total_pendientes'   => 'integer',
    ];

    public const ESTADO_BORRADOR    = 'borrador';
    public const ESTADO_PROGRAMADA  = 'programada';
    public const ESTADO_CORRIENDO   = 'corriendo';
    public const ESTADO_PAUSADA     = 'pausada';
    public const ESTADO_COMPLETADA  = 'completada';
    public const ESTADO_CANCELADA   = 'cancelada';

    public function destinatarios(): HasMany
    {
        return $this->hasMany(CampanaDestinatario::class, 'campana_id');
    }

    public function enHorario(): bool
    {
        $ahora = now('America/Bogota')->format('H:i:s');
        return $ahora >= ($this->ventana_desde ?: '00:00:00')
            && $ahora <= ($this->ventana_hasta ?: '23:59:59');
    }
}
