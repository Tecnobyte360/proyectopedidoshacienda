<?php

namespace App\Livewire\Categorias;

use App\Models\ProductoCategoria;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalAbierto = false;
    public ?int $editandoId = null;

    public string $nombre        = '';
    public string $descripcion   = '';
    public string $icono_emoji   = '';
    public string $color         = '#d68643';
    public int    $orden         = 0;
    public bool   $activo        = true;

    protected function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'icono_emoji' => 'nullable|string|max:8',
            'color'       => 'nullable|string|max:16',
            'orden'       => 'integer|min:0',
            'activo'      => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $cat = ProductoCategoria::findOrFail($id);

        $this->editandoId  = $cat->id;
        $this->nombre      = $cat->nombre;
        $this->descripcion = (string) $cat->descripcion;
        $this->icono_emoji = (string) $cat->icono_emoji;
        $this->color       = (string) ($cat->color ?? '#d68643');
        $this->orden       = (int) $cat->orden;
        $this->activo      = (bool) $cat->activo;

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function guardar(): void
    {
        $data = $this->validate();

        ProductoCategoria::updateOrCreate(
            ['id' => $this->editandoId],
            $data
        );

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Categoría actualizada.' : 'Categoría creada.',
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $cat = ProductoCategoria::findOrFail($id);
        $cat->activo = !$cat->activo;
        $cat->save();
    }

    public function eliminar(int $id): void
    {
        $cat = ProductoCategoria::withCount('productos')->findOrFail($id);

        if ($cat->productos_count > 0) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "No se puede eliminar: tiene {$cat->productos_count} productos asociados.",
            ]);
            return;
        }

        $cat->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Categoría eliminada.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId  = null;
        $this->nombre      = '';
        $this->descripcion = '';
        $this->icono_emoji = '';
        $this->color       = '#d68643';
        $this->orden       = 0;
        $this->activo      = true;
        $this->resetValidation();
    }

    public function render()
    {
        $categorias = ProductoCategoria::query()
            ->withCount('productos')
            ->when($this->search, fn ($q) =>
                $q->where('nombre', 'like', "%{$this->search}%")
            )
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(15);

        return view('livewire.categorias.index', compact('categorias'))
            ->layout('layouts.app');
    }
}
