<?php

namespace App\Livewire\Ans;

use App\Models\AnsTiempoPedido;
use Livewire\Component;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string $estado            = 'nuevo';
    public string $nombre            = '';
    public string $descripcion       = '';
    public int    $minutos_objetivo  = 5;
    public int    $minutos_alerta    = 8;
    public int    $minutos_critico   = 12;
    public int    $orden             = 0;
    public bool   $activo            = true;

    public array $estadosDisponibles = [
        'nuevo'                 => 'Nuevo (atención inicial)',
        'en_preparacion'        => 'En preparación',
        'repartidor_en_camino'  => 'Repartidor en camino',
    ];

    protected function rules(): array
    {
        return [
            'estado'           => 'required|string|max:60',
            'nombre'           => 'required|string|max:120',
            'descripcion'      => 'nullable|string|max:255',
            'minutos_objetivo' => 'required|integer|min:1|max:1440',
            'minutos_alerta'   => 'required|integer|min:1|max:1440|gte:minutos_objetivo',
            'minutos_critico'  => 'required|integer|min:1|max:1440|gte:minutos_alerta',
            'orden'            => 'integer|min:0',
            'activo'           => 'boolean',
        ];
    }

    protected $messages = [
        'minutos_alerta.gte'  => 'La alerta debe ser mayor o igual al objetivo.',
        'minutos_critico.gte' => 'El crítico debe ser mayor o igual a la alerta.',
    ];

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $ans = AnsTiempoPedido::findOrFail($id);

        $this->editandoId       = $ans->id;
        $this->estado           = $ans->estado;
        $this->nombre           = $ans->nombre;
        $this->descripcion      = (string) $ans->descripcion;
        $this->minutos_objetivo = (int) $ans->minutos_objetivo;
        $this->minutos_alerta   = (int) $ans->minutos_alerta;
        $this->minutos_critico  = (int) $ans->minutos_critico;
        $this->orden            = (int) $ans->orden;
        $this->activo           = (bool) $ans->activo;

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

        AnsTiempoPedido::updateOrCreate(
            ['id' => $this->editandoId],
            $data
        );

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'ANS actualizado.' : 'ANS creado.',
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $ans = AnsTiempoPedido::findOrFail($id);
        $ans->activo = !$ans->activo;
        $ans->save();
    }

    public function eliminar(int $id): void
    {
        AnsTiempoPedido::findOrFail($id)->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'ANS eliminado.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId       = null;
        $this->estado           = 'nuevo';
        $this->nombre           = '';
        $this->descripcion      = '';
        $this->minutos_objetivo = 5;
        $this->minutos_alerta   = 8;
        $this->minutos_critico  = 12;
        $this->orden            = 0;
        $this->activo           = true;
        $this->resetValidation();
    }

    public function render()
    {
        $ans = AnsTiempoPedido::orderBy('orden')->orderBy('estado')->get();

        return view('livewire.ans.index', compact('ans'))
            ->layout('layouts.app');
    }
}
