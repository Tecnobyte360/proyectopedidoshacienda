<?php

namespace App\Livewire\Promociones;

use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Sede;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $filtroEstado = 'todas';

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string  $nombre        = '';
    public string  $descripcion   = '';
    public string  $tipo          = Promocion::TIPO_PORCENTAJE;
    public float   $valor         = 0;
    public ?int    $compra        = null;
    public ?int    $paga          = null;
    public ?string $fecha_inicio  = null;
    public ?string $fecha_fin     = null;
    public string  $imagen_url    = '';
    public string  $codigo_cupon  = '';
    public bool    $activa                 = true;
    public bool    $aplica_todos_productos = false;
    public bool    $aplica_todas_sedes     = true;
    public int     $orden                  = 0;

    public array $productosSeleccionados = [];
    public array $sedesSeleccionadas     = [];

    protected function rules(): array
    {
        return [
            'nombre'                 => 'required|string|max:160',
            'descripcion'            => 'nullable|string|max:255',
            'tipo'                   => 'required|in:porcentaje,monto_fijo,precio_especial,nx1',
            'valor'                  => 'required|numeric|min:0',
            'compra'                 => 'nullable|integer|min:1',
            'paga'                   => 'nullable|integer|min:1',
            'fecha_inicio'           => 'nullable|date',
            'fecha_fin'              => 'nullable|date|after_or_equal:fecha_inicio',
            'imagen_url'             => 'nullable|url|max:500',
            'codigo_cupon'           => 'nullable|string|max:60',
            'activa'                 => 'boolean',
            'aplica_todos_productos' => 'boolean',
            'aplica_todas_sedes'     => 'boolean',
            'orden'                  => 'integer|min:0',
        ];
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $promo = Promocion::with(['productos', 'sedes'])->findOrFail($id);

        $this->editandoId             = $promo->id;
        $this->nombre                 = $promo->nombre;
        $this->descripcion            = (string) $promo->descripcion;
        $this->tipo                   = $promo->tipo;
        $this->valor                  = (float) $promo->valor;
        $this->compra                 = $promo->compra;
        $this->paga                   = $promo->paga;
        $this->fecha_inicio           = $promo->fecha_inicio?->format('Y-m-d\TH:i');
        $this->fecha_fin              = $promo->fecha_fin?->format('Y-m-d\TH:i');
        $this->imagen_url             = (string) $promo->imagen_url;
        $this->codigo_cupon           = (string) $promo->codigo_cupon;
        $this->activa                 = (bool) $promo->activa;
        $this->aplica_todos_productos = (bool) $promo->aplica_todos_productos;
        $this->aplica_todas_sedes     = (bool) $promo->aplica_todas_sedes;
        $this->orden                  = (int) $promo->orden;

        $this->productosSeleccionados = $promo->productos->pluck('id')->toArray();
        $this->sedesSeleccionadas     = $promo->sedes->pluck('id')->toArray();

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

        // Normalizar campos opcionales
        $data['compra']       = $this->tipo === Promocion::TIPO_NX1 ? $this->compra : null;
        $data['paga']         = $this->tipo === Promocion::TIPO_NX1 ? $this->paga   : null;
        $data['codigo_cupon'] = $this->codigo_cupon !== '' ? $this->codigo_cupon : null;

        $promo = Promocion::updateOrCreate(
            ['id' => $this->editandoId],
            $data
        );

        $promo->productos()->sync($this->aplica_todos_productos ? [] : $this->productosSeleccionados);
        $promo->sedes()->sync($this->aplica_todas_sedes ? [] : $this->sedesSeleccionadas);

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Promoción actualizada.' : 'Promoción creada.',
        ]);
    }

    public function toggleActiva(int $id): void
    {
        $p = Promocion::findOrFail($id);
        $p->activa = !$p->activa;
        $p->save();
    }

    public function eliminar(int $id): void
    {
        Promocion::findOrFail($id)->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Promoción eliminada.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId             = null;
        $this->nombre                 = '';
        $this->descripcion            = '';
        $this->tipo                   = Promocion::TIPO_PORCENTAJE;
        $this->valor                  = 0;
        $this->compra                 = null;
        $this->paga                   = null;
        $this->fecha_inicio           = null;
        $this->fecha_fin              = null;
        $this->imagen_url             = '';
        $this->codigo_cupon           = '';
        $this->activa                 = true;
        $this->aplica_todos_productos = false;
        $this->aplica_todas_sedes     = true;
        $this->orden                  = 0;
        $this->productosSeleccionados = [];
        $this->sedesSeleccionadas     = [];
        $this->resetValidation();
    }

    public function render()
    {
        $promociones = Promocion::query()
            ->with(['productos', 'sedes'])
            ->when($this->search, fn ($q) => $q->where('nombre', 'like', "%{$this->search}%"))
            ->when($this->filtroEstado === 'vigentes', fn ($q) => $q->vigentes())
            ->when($this->filtroEstado === 'inactivas', fn ($q) => $q->where('activa', false))
            ->orderBy('orden')
            ->orderByDesc('id')
            ->paginate(12);

        $productos = Producto::activos()->orderBy('nombre')->get();
        $sedes     = Sede::orderBy('nombre')->get();

        return view('livewire.promociones.index', compact('promociones', 'productos', 'sedes'))
            ->layout('layouts.app');
    }
}
