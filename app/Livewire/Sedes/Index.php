<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use App\Services\GeocodingService;
use Livewire\Component;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string  $nombre          = '';
    public string  $direccion       = '';
    public ?float  $latitud         = null;
    public ?float  $longitud        = null;
    public bool    $activa          = true;
    public string  $mensaje_cerrado = '';

    /** Array editable: [dia_key => ['abierto'=>bool, 'abre'=>'HH:MM', 'cierra'=>'HH:MM']] */
    public array $horarios = [];

    protected function rules(): array
    {
        return [
            'nombre'          => 'required|string|max:120',
            'direccion'       => 'nullable|string|max:255',
            'latitud'         => 'nullable|numeric|between:-90,90',
            'longitud'        => 'nullable|numeric|between:-180,180',
            'activa'          => 'boolean',
            'mensaje_cerrado' => 'nullable|string|max:500',
            'horarios'        => 'array',
        ];
    }

    public function mount(): void
    {
        $this->resetCampos();
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $sede = Sede::findOrFail($id);

        $this->editandoId      = $sede->id;
        $this->nombre          = $sede->nombre;
        $this->direccion       = (string) $sede->direccion;
        $this->latitud         = $sede->latitud;
        $this->longitud        = $sede->longitud;
        $this->activa          = (bool) $sede->activa;
        $this->mensaje_cerrado = (string) $sede->mensaje_cerrado;

        // Cargar horarios existentes o defaults
        $existentes = $sede->horarios ?? [];
        $this->horarios = [];
        foreach (Sede::DIAS_SEMANA as $key => $label) {
            $this->horarios[$key] = [
                'abierto' => $existentes[$key]['abierto'] ?? ($key === 'domingo' ? false : true),
                'abre'    => $existentes[$key]['abre']    ?? '08:00',
                'cierra'  => $existentes[$key]['cierra']  ?? ($key === 'sabado' ? '16:00' : '20:00'),
            ];
        }

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function geocodificarDireccion(): void
    {
        if (empty(trim($this->direccion))) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Escribe primero una dirección.']);
            return;
        }

        $g = app(GeocodingService::class)->geocodificar($this->direccion, null, 'Bello');
        if ($g) {
            $this->latitud  = $g['lat'];
            $this->longitud = $g['lng'];
            $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Coordenadas obtenidas.']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => '❌ No se pudo geocodificar esa dirección.']);
        }
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['horarios'] = $this->horarios;

        Sede::updateOrCreate(['id' => $this->editandoId], $data);

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? '✅ Sede actualizada.' : '✅ Sede creada.',
        ]);
    }

    public function toggleActiva(int $id): void
    {
        $s = Sede::findOrFail($id);
        $s->activa = !$s->activa;
        $s->save();
    }

    public function eliminar(int $id): void
    {
        $sede = Sede::find($id);
        if (!$sede) return;

        // No eliminar si tiene pedidos
        if (\App\Models\Pedido::where('sede_id', $id)->exists()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'No se puede eliminar — la sede tiene pedidos asociados. Desactívala mejor.',
            ]);
            return;
        }

        $sede->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Sede eliminada.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId      = null;
        $this->nombre          = '';
        $this->direccion       = '';
        $this->latitud         = null;
        $this->longitud        = null;
        $this->activa          = true;
        $this->mensaje_cerrado = '';

        // Defaults: L-V 8a8, S 9a4, D cerrado
        $this->horarios = [];
        foreach (Sede::DIAS_SEMANA as $key => $label) {
            $this->horarios[$key] = [
                'abierto' => $key !== 'domingo',
                'abre'    => '08:00',
                'cierra'  => $key === 'sabado' ? '16:00' : '20:00',
            ];
        }

        $this->resetValidation();
    }

    public function render()
    {
        $sedes = Sede::orderBy('nombre')->get();
        return view('livewire.sedes.index', compact('sedes'))->layout('layouts.app');
    }
}
