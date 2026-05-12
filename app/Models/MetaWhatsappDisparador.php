<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetaWhatsappDisparador extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_whatsapp_disparadores';

    protected $fillable = [
        'tenant_id', 'evento', 'plantilla_id', 'variables_map', 'activo', 'descripcion',
    ];

    protected $casts = [
        'variables_map' => 'array',
        'activo' => 'boolean',
    ];

    public function plantilla()
    {
        return $this->belongsTo(MetaWhatsappPlantilla::class, 'plantilla_id');
    }

    /**
     * Eventos predefinidos del sistema. La UI usa estos como sugerencia
     * (datalist) pero acepta cualquier string snake_case.
     */
    public static function eventosSugeridos(): array
    {
        return [
            'pedido_confirmado'  => 'Pedido confirmado',
            'pedido_en_proceso'  => 'Pedido en proceso',
            'pedido_en_camino'   => 'Pedido en camino',
            'pedido_entregado'   => 'Pedido entregado',
            'pedido_cancelado'   => 'Pedido cancelado',
            'encuesta_entrega'   => 'Encuesta post-entrega',
            'cumpleanos'         => 'Felicitación de cumpleaños',
            'bienvenida'         => 'Bienvenida (primer mensaje)',
            'recordatorio_pago'  => 'Recordatorio de pago',
        ];
    }
}
