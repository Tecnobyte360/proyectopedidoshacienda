<?php

namespace App\Events;

use App\Models\MensajeWhatsapp;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeWhatsappNuevo implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public MensajeWhatsapp $mensaje) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('chat'),                                          // canal global
            new Channel('chat.' . $this->mensaje->conversacion_id),       // canal específico
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.nuevo';
    }

    public function broadcastWith(): array
    {
        $m = $this->mensaje;
        $conv = $m->conversacion;

        return [
            'id'              => $m->id,
            'conversacion_id' => $m->conversacion_id,
            'rol'             => $m->rol,
            'tipo'            => $m->tipo,
            'contenido'       => $m->contenido,
            'meta'            => $m->meta,
            'created_at'      => $m->created_at?->toIso8601String(),

            'conversacion' => [
                'id'                  => $conv?->id,
                'cliente_nombre'      => $conv?->cliente?->nombre ?? 'Cliente',
                'telefono_normalizado'=> $conv?->telefono_normalizado,
                'estado'              => $conv?->estado,
                'atendida_por_humano' => (bool) $conv?->atendida_por_humano,
                'total_mensajes'      => $conv?->total_mensajes,
            ],
        ];
    }
}
