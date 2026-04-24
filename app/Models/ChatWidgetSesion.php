<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatWidgetSesion extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_widget_sesiones';

    protected $fillable = [
        'widget_id', 'tenant_id',
        'session_id',
        'visitante_nombre', 'visitante_telefono', 'visitante_email',
        'url_origen', 'ip', 'user_agent',
        'total_mensajes', 'ultimo_mensaje_at',
    ];

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'widget_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ChatWidgetMensaje::class, 'sesion_id');
    }
}
