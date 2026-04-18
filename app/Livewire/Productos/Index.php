<?php

namespace App\Livewire\Productos;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\Sede;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    public string $search        = '';
    public string $filtroEstado  = 'todos';   // todos | activos | inactivos
    public ?int $filtroCategoria = null;

    public string $vista = 'tabla';   // tabla | grid

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    // Campos del producto
    public ?int   $categoria_id = null;
    public string $codigo            = '';
    public string $nombre            = '';
    public string $descripcion       = '';
    public string $descripcion_corta = '';
    public string $unidad            = 'unidad';
    public float  $precio_base       = 0;
    public string $imagen_url        = '';
    public ?string $imagen_path      = null;
    public $imagenFile               = null;   // archivo temporal Livewire
    public string $palabrasClaveTexto = '';
    public bool   $activo    = true;
    public bool   $destacado = false;
    public int    $orden     = 0;

    // Precios por sede: ['sede_id' => ['precio'=>..., 'disponible'=>bool]]
    public array $preciosSedes = [];

    protected function rules(): array
    {
        return [
            'categoria_id'      => 'nullable|exists:productos_categorias,id',
            'codigo'            => 'nullable|string|max:60',
            'nombre'            => 'required|string|max:160',
            'descripcion'       => 'nullable|string',
            'descripcion_corta' => 'nullable|string|max:255',
            'unidad'            => 'required|string|max:32',
            'precio_base'       => 'required|numeric|min:0',
            'imagen_url'        => 'nullable|url|max:500',
            'imagenFile'        => 'nullable|image|max:2048|mimes:jpg,jpeg,png,webp',
            'activo'            => 'boolean',
            'destacado'         => 'boolean',
            'orden'             => 'integer|min:0',
        ];
    }

    public function updatingSearch(): void          { $this->resetPage(); }
    public function updatingFiltroEstado(): void    { $this->resetPage(); }
    public function updatingFiltroCategoria(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->cargarSedesVacias();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $producto = Producto::with('sedes')->findOrFail($id);

        $this->editandoId        = $producto->id;
        $this->categoria_id      = $producto->categoria_id;
        $this->codigo            = (string) $producto->codigo;
        $this->nombre            = $producto->nombre;
        $this->descripcion       = (string) $producto->descripcion;
        $this->descripcion_corta = (string) $producto->descripcion_corta;
        $this->unidad            = $producto->unidad;
        $this->precio_base       = (float) $producto->precio_base;
        $this->imagen_url        = (string) $producto->imagen_url;
        $this->imagen_path       = $producto->imagen_path;
        $this->imagenFile        = null;
        $this->palabrasClaveTexto = implode(', ', $producto->palabras_clave ?? []);
        $this->activo            = (bool) $producto->activo;
        $this->destacado         = (bool) $producto->destacado;
        $this->orden             = (int) $producto->orden;

        $this->cargarSedesVacias();

        foreach ($producto->sedes as $sede) {
            $this->preciosSedes[$sede->id] = [
                'precio'     => $sede->pivot->precio,
                'disponible' => (bool) $sede->pivot->disponible,
                'nota_sede'  => $sede->pivot->nota_sede,
            ];
        }

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

        // Quitamos el archivo del array de datos — se procesa aparte
        unset($data['imagenFile']);

        $palabras = collect(explode(',', $this->palabrasClaveTexto))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->values()
            ->all();

        $data['palabras_clave'] = $palabras;
        $data['imagen_path'] = $this->imagen_path;   // mantener el path actual si no hay archivo nuevo

        $producto = Producto::updateOrCreate(
            ['id' => $this->editandoId],
            $data
        );

        // Si subió archivo nuevo, guardarlo y actualizar imagen_path
        if ($this->imagenFile) {
            // Borrar la imagen anterior si existía
            if ($producto->imagen_path && Storage::disk('public')->exists($producto->imagen_path)) {
                Storage::disk('public')->delete($producto->imagen_path);
            }

            $ext  = $this->imagenFile->getClientOriginalExtension() ?: 'jpg';
            $name = "productos/{$producto->id}_" . time() . '.' . strtolower($ext);
            $this->imagenFile->storePubliclyAs('', $name, 'public');

            $producto->update(['imagen_path' => $name]);
        }

        // Sincronizar precios por sede
        $sync = [];
        foreach ($this->preciosSedes as $sedeId => $info) {
            $sync[$sedeId] = [
                'precio'     => $info['precio'] !== '' && $info['precio'] !== null ? (float) $info['precio'] : null,
                'disponible' => (bool) ($info['disponible'] ?? false),
                'nota_sede'  => $info['nota_sede'] ?? null,
            ];
        }
        $producto->sedes()->sync($sync);

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Producto actualizado.' : 'Producto creado.',
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $p = Producto::findOrFail($id);
        $p->activo = !$p->activo;
        $p->save();
    }

    public function toggleDestacado(int $id): void
    {
        $p = Producto::findOrFail($id);
        $p->destacado = !$p->destacado;
        $p->save();
    }

    public function quitarImagenActual(): void
    {
        if ($this->imagen_path && Storage::disk('public')->exists($this->imagen_path)) {
            Storage::disk('public')->delete($this->imagen_path);
        }

        $this->imagen_path = null;
        $this->imagenFile = null;

        if ($this->editandoId) {
            Producto::where('id', $this->editandoId)->update(['imagen_path' => null]);
        }
    }

    public function eliminar(int $id): void
    {
        Producto::findOrFail($id)->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Producto eliminado.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId         = null;
        $this->categoria_id       = null;
        $this->codigo             = '';
        $this->nombre             = '';
        $this->descripcion        = '';
        $this->descripcion_corta  = '';
        $this->unidad             = 'unidad';
        $this->precio_base        = 0;
        $this->imagen_url         = '';
        $this->imagen_path        = null;
        $this->imagenFile         = null;
        $this->palabrasClaveTexto = '';
        $this->activo             = true;
        $this->destacado          = false;
        $this->orden              = 0;
        $this->preciosSedes       = [];
        $this->resetValidation();
    }

    private function cargarSedesVacias(): void
    {
        $this->preciosSedes = [];

        foreach (Sede::orderBy('nombre')->get() as $sede) {
            $this->preciosSedes[$sede->id] = [
                'precio'     => null,
                'disponible' => true,
                'nota_sede'  => null,
            ];
        }
    }

    public function render()
    {
        $productos = Producto::query()
            ->with(['categoria', 'sedes'])
            ->when($this->search, fn ($q) =>
                $q->where(function ($qq) {
                    $qq->where('nombre', 'like', "%{$this->search}%")
                       ->orWhere('codigo', 'like', "%{$this->search}%");
                })
            )
            ->when($this->filtroEstado === 'activos',   fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($this->filtroCategoria, fn ($q) => $q->where('categoria_id', $this->filtroCategoria))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(15);

        $categorias = ProductoCategoria::orderBy('nombre')->get();
        $sedes      = Sede::orderBy('nombre')->get();

        return view('livewire.productos.index', compact('productos', 'categorias', 'sedes'))
            ->layout('layouts.app');
    }
}
