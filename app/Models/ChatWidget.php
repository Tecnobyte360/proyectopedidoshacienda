<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatWidget extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_widgets';

    protected $fillable = [
        'tenant_id', 'token', 'nombre',
        'color_primario', 'color_secundario', 'posicion',
        'titulo', 'subtitulo', 'saludo_inicial', 'placeholder', 'avatar_url',
        'dominios_permitidos',
        'activo', 'pedir_nombre', 'pedir_telefono', 'sonido_notificacion',
        'total_conversaciones', 'total_mensajes',
    ];

    protected $casts = [
        'activo'              => 'boolean',
        'pedir_nombre'        => 'boolean',
        'pedir_telefono'      => 'boolean',
        'sonido_notificacion' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($w) {
            if (empty($w->token)) {
                $w->token = Str::random(32);
            }
        });
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(ChatWidgetSesion::class, 'widget_id');
    }

    /**
     * ¿El request viene de un dominio permitido?
     */
    public function dominioAutorizado(?string $origin): bool
    {
        $lista = trim((string) $this->dominios_permitidos);
        if ($lista === '') return true;   // vacío = cualquier origen

        $dominios = array_values(array_filter(array_map('trim', explode(',', $lista))));
        if (empty($dominios)) return true;

        if (!$origin) return false;
        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;

        foreach ($dominios as $d) {
            if ($d === '*') return true;
            if ($host === $d) return true;
            if (str_ends_with($host, '.' . $d)) return true;   // subdominio
        }
        return false;
    }
}
