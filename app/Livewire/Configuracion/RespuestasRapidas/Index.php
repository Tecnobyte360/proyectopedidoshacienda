<?php

namespace App\Livewire\Configuracion\RespuestasRapidas;

use App\Models\RespuestaRapida;
use Livewire\Component;

class Index extends Component
{
    public bool   $modal      = false;
    public ?int   $editandoId = null;
    public string $atajo      = '';
    public string $texto      = '';
    public int    $orden      = 0;
    public bool   $activa     = true;

    public string $busqueda = '';

    protected function rules(): array
    {
        return [
            'atajo'  => 'nullable|string|max:40',
            'texto'  => 'required|string|max:2000',
            'orden'  => 'integer|min:0|max:9999',
            'activa' => 'boolean',
        ];
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'atajo', 'texto', 'orden', 'activa']);
        $this->activa = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $r = RespuestaRapida::findOrFail($id);
        $this->editandoId = $r->id;
        $this->atajo      = (string) $r->atajo;
        $this->texto      = (string) $r->texto;
        $this->orden      = (int) $r->orden;
        $this->activa     = (bool) $r->activa;
        $this->modal = true;
    }

    public function cerrarModal(): void
    {
        $this->modal = false;
    }

    public function guardar(): void
    {
        $this->validate();

        $data = [
            'atajo'  => trim($this->atajo) ?: null,
            'texto'  => $this->texto,
            'orden'  => $this->orden,
            'activa' => $this->activa,
        ];

        if ($this->editandoId) {
            RespuestaRapida::findOrFail($this->editandoId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Respuesta actualizada']);
        } else {
            RespuestaRapida::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Respuesta creada']);
        }

        $this->modal = false;
    }

    public function eliminar(int $id): void
    {
        RespuestaRapida::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Eliminada']);
    }

    public function toggleActiva(int $id): void
    {
        $r = RespuestaRapida::findOrFail($id);
        $r->update(['activa' => !$r->activa]);
    }

    public function render()
    {
        $items = RespuestaRapida::query()
            ->when($this->busqueda, fn($q) => $q->where(fn($qq) => $qq
                ->where('atajo', 'like', "%{$this->busqueda}%")
                ->orWhere('texto', 'like', "%{$this->busqueda}%")))
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return view('livewire.configuracion.respuestas-rapidas.index', compact('items'))
            ->layout('layouts.app');
    }
}
