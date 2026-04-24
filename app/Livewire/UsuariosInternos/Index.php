<?php

namespace App\Livewire\UsuariosInternos;

use App\Models\UsuarioInternoWhatsapp;
use Livewire\Component;

class Index extends Component
{
    public bool $modal = false;
    public ?int $editandoId = null;

    public string $telefono     = '';
    public string $nombre       = '';
    public string $cargo        = '';
    public string $departamento = '';
    public string $notas        = '';
    public bool   $activo       = true;

    public string $busqueda = '';

    protected function rules(): array
    {
        return [
            'telefono' => 'required|string|max:20',
            'nombre'   => 'required|string|max:120',
            'cargo'    => 'nullable|string|max:120',
            'departamento' => 'nullable|string|max:120',
            'notas'    => 'nullable|string|max:500',
        ];
    }

    public function render()
    {
        $usuarios = UsuarioInternoWhatsapp::query()
            ->when($this->busqueda, fn ($q) =>
                $q->where(fn ($qq) =>
                    $qq->where('nombre', 'like', "%{$this->busqueda}%")
                       ->orWhere('telefono_normalizado', 'like', "%{$this->busqueda}%")
                       ->orWhere('cargo', 'like', "%{$this->busqueda}%")
                )
            )
            ->orderBy('nombre')
            ->get();

        return view('livewire.usuarios-internos.index', compact('usuarios'))
            ->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'telefono', 'nombre', 'cargo', 'departamento', 'notas']);
        $this->activo = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $u = UsuarioInternoWhatsapp::findOrFail($id);
        $this->editandoId   = $u->id;
        $this->telefono     = $u->telefono_normalizado;
        $this->nombre       = $u->nombre;
        $this->cargo        = (string) $u->cargo;
        $this->departamento = (string) $u->departamento;
        $this->notas        = (string) $u->notas;
        $this->activo       = $u->activo;
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['telefono_normalizado'] = preg_replace('/\D+/', '', $this->telefono);
        unset($data['telefono']);
        $data['activo'] = $this->activo;

        if ($this->editandoId) {
            UsuarioInternoWhatsapp::findOrFail($this->editandoId)->update($data);
        } else {
            UsuarioInternoWhatsapp::create($data);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Guardado']);
    }

    public function toggleActivo(int $id): void
    {
        $u = UsuarioInternoWhatsapp::findOrFail($id);
        $u->update(['activo' => !$u->activo]);
    }

    public function eliminar(int $id): void
    {
        UsuarioInternoWhatsapp::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Eliminado']);
    }
}
