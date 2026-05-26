<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;

class LlamadaIvr extends Model
{
    use BelongsToTenant;

    protected $table = 'llamadas_ivr';

    protected $fillable = [
        'tenant_id',
        'asterisk_uniqueid',
        'caller_id',
        'telefono_normalizado',
        'did_destino',
        'cliente_id',
        'estado',
        'opcion_elegida',
        'pedido_consultado_id',
        'transferida',
        'transferida_a',
        'asesor_contesto',
        'dejo_voicemail',
        'voicemail_path',
        'duracion_segundos',
        'iniciada_at',
        'terminada_at',
        'eventos',
    ];

    protected $casts = [
        'iniciada_at'      => 'datetime',
        'terminada_at'     => 'datetime',
        'transferida'      => 'boolean',
        'asesor_contesto'  => 'boolean',
        'dejo_voicemail'   => 'boolean',
        'duracion_segundos'=> 'integer',
        'eventos'          => 'array',
    ];

    public function cliente() { return $this->belongsTo(Cliente::class); }
    public function pedido()  { return $this->belongsTo(Pedido::class, 'pedido_consultado_id'); }

    public function agregarEvento(string $tipo, array $data = []): void
    {
        $eventos = $this->eventos ?? [];
        $eventos[] = array_merge(['tipo' => $tipo, 'ts' => now()->toIso8601String()], $data);
        $this->eventos = $eventos;
        $this->save();
    }
}
