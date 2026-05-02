<?php

namespace App\Livewire\Conversaciones;

use App\Models\ConversacionWhatsapp;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $filtroEstado = 'todas';   // todas | activa | cerrada | con_pedido | sin_pedido
    public string $orden        = 'recientes';

    public ?int $verConversacionId = null;

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingOrden(): void        { $this->resetPage(); }

    public function ver(int $id): void
    {
        $this->verConversacionId = $id;
    }

    public function cerrarVer(): void
    {
        $this->verConversacionId = null;
    }

    public function archivar(int $id): void
    {
        $c = ConversacionWhatsapp::findOrFail($id);
        $c->update(['estado' => ConversacionWhatsapp::ESTADO_ARCHIVADA]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Conversación archivada.',
        ]);
    }

    public function eliminar(int $id): void
    {
        ConversacionWhatsapp::findOrFail($id)->delete();
        $this->verConversacionId = null;

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Conversación eliminada.',
        ]);
    }

    /**
     * Resetea el contexto cacheado del bot para una conversación.
     * Útil cuando el bot está respondiendo cosas viejas y el cliente
     * empieza un tema nuevo.
     */
    public function resetearContexto(int $id): void
    {
        $conv = ConversacionWhatsapp::findOrFail($id);
        $tenantId = $conv->tenant_id ?? 'none';
        $telefono = $conv->telefono_normalizado;

        \Illuminate\Support\Facades\Cache::forget("whatsapp_chat_t{$tenantId}_{$telefono}");
        \Illuminate\Support\Facades\Cache::forget("wa_rechazo_cobertura_idx_t{$tenantId}_{$telefono}");

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Contexto reseteado. El próximo mensaje del cliente arranca de cero.',
        ]);
    }

    public function render()
    {
        $query = ConversacionWhatsapp::query()
            ->with(['cliente', 'sede', 'pedido'])
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('telefono_normalizado', 'like', "%{$this->search}%")
                       ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->filtroEstado === 'activa',     fn ($q) => $q->where('estado', 'activa'))
            ->when($this->filtroEstado === 'cerrada',    fn ($q) => $q->where('estado', 'cerrada'))
            ->when($this->filtroEstado === 'con_pedido', fn ($q) => $q->where('genero_pedido', true))
            ->when($this->filtroEstado === 'sin_pedido', fn ($q) => $q->where('genero_pedido', false));

        $query = match ($this->orden) {
            'mas_mensajes' => $query->orderByDesc('total_mensajes'),
            'antiguas'     => $query->orderBy('ultimo_mensaje_at'),
            default        => $query->orderByDesc('ultimo_mensaje_at'),
        };

        $conversaciones = $query->paginate(20);

        $verConversacion = $this->verConversacionId
            ? ConversacionWhatsapp::with(['cliente', 'mensajes', 'pedido'])->find($this->verConversacionId)
            : null;

        // KPIs
        $totales = [
            'total'       => ConversacionWhatsapp::count(),
            'activas'     => ConversacionWhatsapp::where('estado', 'activa')->count(),
            'con_pedido'  => ConversacionWhatsapp::where('genero_pedido', true)->count(),
            'mensajes'    => ConversacionWhatsapp::sum('total_mensajes'),
        ];

        return view('livewire.conversaciones.index', compact('conversaciones', 'verConversacion', 'totales'))
            ->layout('layouts.app');
    }
}
