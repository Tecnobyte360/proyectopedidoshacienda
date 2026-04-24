<?php

namespace App\Livewire\Cortes;

use App\Models\Corte;
use App\Models\Producto;
use Livewire\Component;

class Index extends Component
{
    public bool $modal = false;
    public ?int $editandoId = null;

    public string $nombre      = '';
    public string $descripcion = '';
    public string $iconoEmoji  = '🔪';
    public int    $orden       = 0;
    public bool   $activo      = true;
    public array  $productoIds = [];      // productos a los que aplica este corte

    public string $busqueda = '';

    protected function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:80',
            'descripcion' => 'nullable|string|max:250',
            'iconoEmoji'  => 'nullable|string|max:10',
        ];
    }

    public function render()
    {
        $cortes = Corte::withCount('productos')
            ->when($this->busqueda, fn ($q) =>
                $q->where('nombre', 'like', "%{$this->busqueda}%")
            )
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $productos = Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);

        return view('livewire.cortes.index', compact('cortes', 'productos'))
            ->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'descripcion', 'productoIds']);
        $this->iconoEmoji = '🔪';
        $this->orden = 0;
        $this->activo = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $c = Corte::findOrFail($id);
        $this->editandoId  = $c->id;
        $this->nombre      = $c->nombre;
        $this->descripcion = (string) $c->descripcion;
        $this->iconoEmoji  = (string) ($c->icono_emoji ?: '🔪');
        $this->orden       = $c->orden;
        $this->activo      = $c->activo;
        $this->productoIds = $c->productos->pluck('id')->map(fn ($x) => (int) $x)->all();
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $data = [
            'nombre'       => $this->nombre,
            'descripcion'  => $this->descripcion ?: null,
            'icono_emoji'  => $this->iconoEmoji ?: null,
            'orden'        => $this->orden,
            'activo'       => $this->activo,
        ];

        $corte = $this->editandoId
            ? tap(Corte::findOrFail($this->editandoId))->update($data)
            : Corte::create($data);

        // Sincronizar productos
        $syncData = [];
        foreach ($this->productoIds as $idx => $pid) {
            $syncData[(int) $pid] = ['orden' => $idx];
        }
        $corte->productos()->sync($syncData);

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Corte guardado']);
    }

    public function toggleActivo(int $id): void
    {
        $c = Corte::findOrFail($id);
        $c->update(['activo' => !$c->activo]);
    }

    public function eliminar(int $id): void
    {
        Corte::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Corte eliminado']);
    }
}
