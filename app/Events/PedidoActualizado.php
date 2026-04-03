<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pedido $pedido, public string $accion = 'actualizado')
    {
        $this->pedido->load(['sede', 'detalles']);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('pedidos');
    }

    public function broadcastAs(): string
    {
        return 'pedido.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                   => $this->pedido->id,
            'accion'               => $this->accion,
            'cliente_nombre'       => $this->pedido->cliente_nombre,
            'telefono'             => $this->pedido->telefono,
            'telefono_whatsapp'    => $this->pedido->telefono_whatsapp,
            'telefono_contacto'    => $this->pedido->telefono_contacto,
            'sede'                 => $this->pedido->sede?->nombre ?? 'No especificada',
            'fecha_pedido'         => optional($this->pedido->fecha_pedido)?->format('d/m/Y H:i'),
            'hora_entrega'         => $this->pedido->hora_entrega ?? 'Por confirmar',
            'estado'               => $this->pedido->estado,
            'total'                => number_format((float) $this->pedido->total, 0, ',', '.'),
            'total_raw'            => (float) $this->pedido->total,
            'detalles'             => $this->formatearDetalles(),
            'created_at'           => optional($this->pedido->created_at)?->format('d/m/Y H:i:s'),
            'resumen_conversacion' => $this->pedido->resumen_conversacion,
        ];
    }

    private function formatearDetalles(): array
    {
        return $this->pedido->detalles->map(function ($detalle) {
            return [
                'producto' => $detalle->producto,
                'cantidad' => $this->formatearCantidad((float) $detalle->cantidad),
                'unidad'   => $detalle->unidad,
                'subtotal' => number_format((float) $detalle->subtotal, 0, ',', '.'),
            ];
        })->toArray();
    }

    private function formatearCantidad(float $cantidad): string
    {
        if (fmod($cantidad, 1) == 0.0) {
            return number_format($cantidad, 0, ',', '.');
        }

        return number_format($cantidad, 2, ',', '.');
    }
}