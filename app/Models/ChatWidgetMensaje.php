<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ChatWidgetMensaje extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_widget_mensajes';

    protected $fillable = [
        'sesion_id', 'tenant_id', 'rol', 'contenido', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public const ROL_USER      = 'user';
    public const ROL_ASSISTANT = 'assistant';
    public const ROL_SYSTEM    = 'system';
}
