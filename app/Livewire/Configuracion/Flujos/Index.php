<?php

namespace App\Livewire\Configuracion\Flujos;

use App\Models\Departamento;
use App\Models\FlujoBot;
use Livewire\Component;

class Index extends Component
{
    public bool   $modalAbierto = false;
    public ?int   $editandoId   = null;

    public string $nombre       = '';
    public string $descripcion  = '';
    public bool   $activo       = true;
    public int    $prioridad    = 0;
    public array  $grafo        = [];

    protected function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:120',
            'descripcion'  => 'nullable|string|max:500',
            'activo'       => 'boolean',
            'prioridad'    => 'integer|min:0|max:1000',
            'grafo'        => 'array',
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'descripcion', 'prioridad']);
        $this->activo = true;
        $this->grafo  = $this->grafoVacio();
        $this->modalAbierto = true;
        $this->dispatch('flujo-cargado', grafo: $this->grafo);
    }

    public function editar(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $this->editandoId  = $f->id;
        $this->nombre      = $f->nombre;
        $this->descripcion = (string) $f->descripcion;
        $this->activo      = (bool) $f->activo;
        $this->prioridad   = (int) $f->prioridad;
        $this->grafo       = is_array($f->grafo) && !empty($f->grafo) ? $f->grafo : $this->grafoVacio();
        $this->modalAbierto = true;
        $this->dispatch('flujo-cargado', grafo: $this->grafo);
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->reset(['editandoId', 'nombre', 'descripcion', 'prioridad', 'grafo']);
    }

    /**
     * Recibe el JSON exportado desde Drawflow y guarda en BD.
     */
    public function guardar(array $grafoExportado = []): void
    {
        if (!empty($grafoExportado)) {
            $this->grafo = $grafoExportado;
        }

        $data = $this->validate();

        FlujoBot::updateOrCreate(['id' => $this->editandoId], $data);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? '✅ Flujo actualizado.' : '✅ Flujo creado.',
        ]);

        $this->cerrarModal();
    }

    public function toggleActivo(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $f->activo = !$f->activo;
        $f->save();
    }

    public function eliminar(int $id): void
    {
        FlujoBot::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Flujo eliminado.']);
    }

    public function duplicar(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $nuevo = $f->replicate();
        $nuevo->nombre = $f->nombre . ' (copia)';
        $nuevo->activo = false;
        $nuevo->save();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Flujo duplicado (queda inactivo).']);
    }

    private function grafoVacio(): array
    {
        return [
            'drawflow' => [
                'Home' => [
                    'data' => [],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.configuracion.flujos.index', [
            'flujos'        => FlujoBot::orderByDesc('prioridad')->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app');
    }
}
