<?php

namespace App\Events;

use App\Models\Domiciliario;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DomiciliarioUbicacion implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Domiciliario $domiciliario) {}

    public function broadcastOn(): array
    {
        $tid = $this->domiciliario->tenant_id ?? 'global';
        return [
            new Channel("domiciliarios.tenant.{$tid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'domiciliario.ubicacion';
    }

    public function broadcastWith(): array
    {
        $d = $this->domiciliario;
        return [
            'id'        => $d->id,
            'nombre'    => $d->nombre,
            'lat'       => (float) $d->lat_actual,
            'lng'       => (float) $d->lng_actual,
            'estado'    => $d->estado,
            'vehiculo'  => $d->vehiculo,
            'placa'     => $d->placa,
            'telefono'  => $d->telefono,
            'updated_at'=> optional($d->ubicacion_actualizada_at)->toIso8601String(),
        ];
    }
}
