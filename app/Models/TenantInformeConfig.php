<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInformeConfig extends Model
{
    protected $table = 'tenant_informe_configs';

    protected $fillable = [
        'tenant_id', 'activo', 'frecuencia', 'dia_semana', 'dia_mes', 'hora_envio',
        'emails', 'telefonos_whatsapp',
        'inc_volumen', 'inc_horas_pico', 'inc_tiempo_respuesta',
        'inc_reacciones', 'inc_top_clientes', 'inc_sin_responder', 'inc_palabras_top',
        'inc_clientes_molestos',
        'ultimo_envio_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'emails' => 'array',
        'telefonos_whatsapp' => 'array',
        'inc_volumen' => 'boolean',
        'inc_horas_pico' => 'boolean',
        'inc_tiempo_respuesta' => 'boolean',
        'inc_reacciones' => 'boolean',
        'inc_top_clientes' => 'boolean',
        'inc_sin_responder' => 'boolean',
        'inc_palabras_top' => 'boolean',
        'inc_clientes_molestos' => 'boolean',
        'ultimo_envio_at' => 'datetime',
        // hora_envio queda como string ya que el column es TIME (no datetime).
        // Lo convertimos manualmente cuando lo necesitamos.
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** ¿Es momento de enviarlo? */
    public function tocaEnviar(\Carbon\Carbon $ahora): bool
    {
        if (!$this->activo) return false;

        // Hora coincide? (margen de 1h: si scheduler corre cada hora)
        $horaConfig = (int) substr($this->hora_envio, 0, 2);
        if ($ahora->hour !== $horaConfig) return false;

        // Frecuencia
        if ($this->frecuencia === 'diario') {
            // Ya se envió hoy? evita dup
            return !$this->ultimo_envio_at || !$this->ultimo_envio_at->isSameDay($ahora);
        }
        if ($this->frecuencia === 'semanal') {
            // Carbon: 1=lunes ... 7=domingo (ISO)
            if ($ahora->dayOfWeekIso !== (int) $this->dia_semana) return false;
            return !$this->ultimo_envio_at || $this->ultimo_envio_at->lt($ahora->copy()->startOfDay());
        }
        if ($this->frecuencia === 'mensual') {
            if ($ahora->day !== (int) $this->dia_mes) return false;
            return !$this->ultimo_envio_at || $this->ultimo_envio_at->lt($ahora->copy()->startOfDay());
        }
        return false;
    }
}
