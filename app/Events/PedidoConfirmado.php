<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoConfirmado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pedido $pedido)
    {
        $this->pedido->load(['sede', 'detalles']);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('pedidos');
    }

    public function broadcastAs(): string
    {
        return 'pedido.confirmado';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                   => $this->pedido->id,
            'cliente_nombre'       => $this->pedido->cliente_nombre,
            'telefono'             => $this->pedido->telefono,
            'sede'                 => $this->pedido->sede?->nombre ?? 'No especificada',
            'fecha_pedido'         => $this->pedido->fecha_pedido->format('d/m/Y'),
            'hora_entrega'         => $this->pedido->hora_entrega ?? 'Por confirmar',
            'estado'               => $this->pedido->estado,
            'total'                => number_format($this->pedido->total, 0, ',', '.'),
            'detalles'             => $this->formatearDetalles(),
            'created_at'           => $this->pedido->created_at->format('d/m/Y H:i:s'),
            'resumen_conversacion' => $this->pedido->resumen_conversacion,
        ];
    }

    private function formatearDetalles(): array
    {
        return $this->pedido->detalles->map(function ($detalle) {
            return [
                'producto' => $detalle->producto,
                'cantidad' => $this->formatearCantidad($detalle->cantidad),
                'unidad'   => $detalle->unidad,
                'subtotal' => number_format($detalle->subtotal, 0, ',', '.'),
            ];
        })->toArray();
    }

    private function formatearCantidad(float $cantidad): string
    {
        // Si es un número entero, mostrar sin decimales
        if (fmod($cantidad, 1) == 0) {
            return number_format($cantidad, 0, ',', '.');
        }
        
        // Si tiene decimales, mostrar con 2 decimales
        return number_format($cantidad, 2, ',', '.');
    }
}