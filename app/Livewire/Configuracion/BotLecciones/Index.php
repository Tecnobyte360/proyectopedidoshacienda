<?php

namespace App\Livewire\Configuracion\BotLecciones;

use App\Models\BotLeccion;
use Livewire\Component;

class Index extends Component
{
    public bool   $modal      = false;
    public ?int   $editandoId = null;
    public string $categoria       = 'general';
    public string $titulo          = '';
    public string $contexto_error  = '';
    public string $regla           = '';
    public string $frase_disparadora = '';
    public bool   $activa = true;

    public string $busqueda = '';
    public string $filtroCategoria = '';

    protected function rules(): array
    {
        return [
            'categoria'         => 'required|string|in:' . implode(',', array_keys(BotLeccion::CATEGORIAS)),
            'titulo'            => 'required|string|max:200',
            'contexto_error'    => 'nullable|string|max:1000',
            'regla'             => 'nullable|string|max:1000',
            'frase_disparadora' => 'nullable|string|max:200',
            'activa'            => 'boolean',
        ];
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'titulo', 'contexto_error', 'regla', 'frase_disparadora']);
        $this->categoria = 'general';
        $this->activa = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $l = BotLeccion::findOrFail($id);
        $this->editandoId        = $l->id;
        $this->categoria         = $l->categoria;
        $this->titulo            = $l->titulo;
        $this->contexto_error    = (string) $l->contexto_error;
        $this->regla             = (string) $l->regla;
        $this->frase_disparadora = (string) $l->frase_disparadora;
        $this->activa            = (bool) $l->activa;
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $data = [
            'categoria'         => $this->categoria,
            'titulo'            => $this->titulo,
            'contexto_error'    => trim($this->contexto_error) ?: null,
            'regla'             => trim($this->regla) ?: null,
            'frase_disparadora' => trim($this->frase_disparadora) ?: null,
            'activa'            => $this->activa,
        ];

        if ($this->editandoId) {
            BotLeccion::findOrFail($this->editandoId)->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lección actualizada']);
        } else {
            $data['reportado_por_user_id'] = auth()->id();
            BotLeccion::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lección creada']);
        }
        $this->modal = false;
    }

    public function eliminar(int $id): void
    {
        BotLeccion::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Eliminada']);
    }

    public function toggleActiva(int $id): void
    {
        $l = BotLeccion::findOrFail($id);
        $l->update(['activa' => !$l->activa]);
    }

    public function render()
    {
        $items = BotLeccion::query()
            ->when($this->busqueda, fn($q) => $q->where(fn($qq) => $qq
                ->where('titulo', 'like', "%{$this->busqueda}%")
                ->orWhere('regla', 'like', "%{$this->busqueda}%")
                ->orWhere('frase_disparadora', 'like', "%{$this->busqueda}%")))
            ->when($this->filtroCategoria, fn($q) => $q->where('categoria', $this->filtroCategoria))
            ->orderByDesc('activa')
            ->orderByDesc('veces_aplicada')
            ->orderByDesc('id')
            ->get();

        return view('livewire.configuracion.bot-lecciones.index', [
            'items'      => $items,
            'categorias' => BotLeccion::CATEGORIAS,
        ])->layout('layouts.app');
    }
}
