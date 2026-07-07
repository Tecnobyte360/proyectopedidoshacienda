<?php

namespace App\Livewire\Impresion;

use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Services\TicketImpresionService;
use Livewire\Component;

/**
 * 🖨️ Panel de impresoras: ver estado del agente, enviar prueba y ver la cola.
 */
class Index extends Component
{
    public function enviarPrueba(int $impresoraId): void
    {
        $imp = Impresora::find($impresoraId);
        if (!$imp) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Impresora no encontrada.']);
            return;
        }

        $contenido = app(TicketImpresionService::class)->ticketPrueba($imp);

        TrabajoImpresion::create([
            'tenant_id'    => $imp->tenant_id,
            'impresora_id' => $imp->id,
            'tipo'         => 'prueba',
            'contenido'    => $contenido,
            'estado'       => TrabajoImpresion::ESTADO_PENDIENTE,
        ]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '🖨️ Prueba enviada a la cola. Debe salir en unos segundos.',
        ]);
    }

    public function render()
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();

        $impresoras = Impresora::where('tenant_id', $tenantId)->orderBy('nombre')->get();

        $trabajos = TrabajoImpresion::where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('livewire.impresion.index', compact('impresoras', 'trabajos'))
            ->layout('layouts.app');
    }
}
