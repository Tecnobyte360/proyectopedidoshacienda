<?php

namespace App\Livewire\Chat;

use App\Models\BotSugerencia;
use App\Models\ConversacionWhatsapp;
use App\Services\BotShadowService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 💡 Copiloto (modo shadow) — componente AISLADO que vive dentro del chat.
 *
 * Genera una sugerencia del bot para el último mensaje del cliente y la
 * muestra al operador con botones. NUNCA envía nada al cliente: cuando el
 * operador elige "usar", solo se copia el texto al composer (vía evento) y
 * el operador decide enviarlo con su dedo.
 */
class BotCopiloto extends Component
{
    public ?int $conversacionId = null;
    public ?int $sugerenciaId = null;
    public string $texto = '';
    public bool $cargando = false;
    public bool $oculto = false;

    public function mount(?int $conversacionId = null): void
    {
        $this->conversacionId = $conversacionId;
    }

    /** Cuando el chat cambia de conversación, reseteamos y generamos. */
    #[On('chat-cambiado')]
    public function alCambiarChat($conversacionId = null): void
    {
        $this->conversacionId = $conversacionId;
        $this->reset(['sugerenciaId', 'texto', 'oculto']);
        $this->generar();
    }

    public function generar(): void
    {
        $this->reset(['sugerenciaId', 'texto', 'oculto']);
        if (!$this->conversacionId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionId);
        if (!$conv) return;

        $this->cargando = true;
        try {
            $sug = app(BotShadowService::class)->sugerirParaConversacion($conv);
            if ($sug && $sug->estado === BotSugerencia::ESTADO_PENDIENTE) {
                $this->sugerenciaId = $sug->id;
                $this->texto = $sug->sugerencia;
            }
        } finally {
            $this->cargando = false;
        }
    }

    /** Copia la sugerencia al composer (NO envía) y la marca como usada. */
    public function usar(): void
    {
        $this->registrar(BotSugerencia::ESTADO_USADA);
        // El Chat\Index escucha esto y pone el texto en el composer
        $this->dispatch('copiloto-usar', texto: $this->texto);
        $this->oculto = true;
    }

    /** Copia al composer para editar; se registra como editada. */
    public function editar(): void
    {
        $this->registrar(BotSugerencia::ESTADO_EDITADA);
        $this->dispatch('copiloto-usar', texto: $this->texto);
        $this->oculto = true;
    }

    public function ignorar(): void
    {
        $this->registrar(BotSugerencia::ESTADO_IGNORADA);
        $this->oculto = true;
    }

    private function registrar(string $accion): void
    {
        if (!$this->sugerenciaId) return;
        $sug = BotSugerencia::find($this->sugerenciaId);
        if (!$sug) return;
        app(BotShadowService::class)->registrarDecision($sug, $accion);
    }

    public function render()
    {
        return view('livewire.chat.bot-copiloto');
    }
}
