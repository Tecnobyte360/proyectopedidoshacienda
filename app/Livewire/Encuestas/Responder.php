<?php

namespace App\Livewire\Encuestas;

use App\Models\EncuestaPedido;
use Livewire\Component;

class Responder extends Component
{
    public string $token;
    public ?EncuestaPedido $encuesta = null;

    public int    $calificacion_proceso       = 0;
    public int    $calificacion_domiciliario  = 0;
    public string $comentario_proceso         = '';
    public string $comentario_domiciliario    = '';
    public ?bool  $recomendaria               = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->encuesta = EncuestaPedido::withoutGlobalScopes()
            ->where('token', $token)
            ->first();

        if (!$this->encuesta) {
            abort(404, 'Encuesta no encontrada o ya expiró.');
        }

        // Marcar como vista la primera vez
        if (!$this->encuesta->vista_at) {
            $this->encuesta->update(['vista_at' => now()]);
        }

        // Si ya respondieron, pre-llenar para mostrar
        if ($this->encuesta->isCompletada()) {
            $this->calificacion_proceso      = (int) $this->encuesta->calificacion_proceso;
            $this->calificacion_domiciliario = (int) $this->encuesta->calificacion_domiciliario;
            $this->comentario_proceso        = (string) $this->encuesta->comentario_proceso;
            $this->comentario_domiciliario   = (string) $this->encuesta->comentario_domiciliario;
            $this->recomendaria              = $this->encuesta->recomendaria;
        }
    }

    protected function rules(): array
    {
        return [
            'calificacion_proceso'      => 'required|integer|min:1|max:5',
            'calificacion_domiciliario' => 'nullable|integer|min:1|max:5',
            'comentario_proceso'        => 'nullable|string|max:1000',
            'comentario_domiciliario'   => 'nullable|string|max:1000',
            'recomendaria'              => 'nullable|boolean',
        ];
    }

    public function setRating(string $campo, int $valor): void
    {
        if (in_array($campo, ['calificacion_proceso', 'calificacion_domiciliario'])) {
            $this->{$campo} = max(0, min(5, $valor));
        }
    }

    public function setRecomendaria(bool $valor): void
    {
        $this->recomendaria = $valor;
    }

    public function guardar(): void
    {
        if ($this->encuesta?->isCompletada()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Esta encuesta ya fue respondida. Gracias.']);
            return;
        }

        $data = $this->validate();

        $this->encuesta->update([
            'calificacion_proceso'      => $data['calificacion_proceso'],
            'calificacion_domiciliario' => $data['calificacion_domiciliario'] ?? null,
            'comentario_proceso'        => $data['comentario_proceso']      ?: null,
            'comentario_domiciliario'   => $data['comentario_domiciliario'] ?: null,
            'recomendaria'              => $data['recomendaria'],
            'completada_at'             => now(),
        ]);

        $this->encuesta->refresh();
        $this->dispatch('notify', ['type' => 'success', 'message' => '¡Gracias por tu opinión!']);
    }

    public function render()
    {
        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($this->encuesta->tenant_id);
        $domiciliario = $this->encuesta->domiciliario_id
            ? \App\Models\Domiciliario::withoutGlobalScopes()->find($this->encuesta->domiciliario_id)
            : null;
        $pedido = \App\Models\Pedido::withoutGlobalScopes()->find($this->encuesta->pedido_id);

        return view('livewire.encuestas.responder', [
            'tenant'       => $tenant,
            'domiciliario' => $domiciliario,
            'pedido'       => $pedido,
        ])->layout('layouts.public', ['pedido' => $pedido]);
    }
}
