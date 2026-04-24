<?php

namespace App\Livewire\Departamentos;

use App\Models\Departamento;
use Livewire\Component;

class Index extends Component
{
    public bool $modal = false;
    public ?int $editandoId = null;

    public string $nombre = '';
    public string $iconoEmoji = '🎯';
    public string $color = '#6366f1';
    public string $keywordsStr = '';                           // CSV editable
    public string $saludoAutomatico = '';
    public bool   $notificarInternos = true;
    public int    $orden = 0;
    public bool   $activo = true;

    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:120',
            'iconoEmoji' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'keywordsStr' => 'nullable|string|max:1000',
            'saludoAutomatico' => 'nullable|string|max:500',
        ];
    }

    public function render()
    {
        return view('livewire.departamentos.index', [
            'departamentos' => Departamento::withCount('usuarios')->orderBy('orden')->orderBy('nombre')->get(),
        ])->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'keywordsStr', 'saludoAutomatico']);
        $this->iconoEmoji = '🎯';
        $this->color = '#6366f1';
        $this->notificarInternos = true;
        $this->orden = 0;
        $this->activo = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $d = Departamento::findOrFail($id);
        $this->editandoId = $d->id;
        $this->nombre = $d->nombre;
        $this->iconoEmoji = (string) ($d->icono_emoji ?: '🎯');
        $this->color = $d->color ?: '#6366f1';
        $this->keywordsStr = implode(', ', $d->keywords ?? []);
        $this->saludoAutomatico = (string) $d->saludo_automatico;
        $this->notificarInternos = (bool) $d->notificar_internos;
        $this->orden = (int) $d->orden;
        $this->activo = (bool) $d->activo;
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $keywords = array_values(array_filter(array_map('trim', explode(',', $this->keywordsStr))));

        $data = [
            'nombre'             => $this->nombre,
            'icono_emoji'        => $this->iconoEmoji,
            'color'              => $this->color,
            'keywords'           => $keywords,
            'saludo_automatico'  => $this->saludoAutomatico ?: null,
            'notificar_internos' => $this->notificarInternos,
            'orden'              => $this->orden,
            'activo'             => $this->activo,
        ];

        if ($this->editandoId) {
            Departamento::findOrFail($this->editandoId)->update($data);
        } else {
            Departamento::create($data);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Guardado']);
    }

    public function toggleActivo(int $id): void
    {
        $d = Departamento::findOrFail($id);
        $d->update(['activo' => !$d->activo]);
    }

    public function eliminar(int $id): void
    {
        Departamento::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Eliminado']);
    }
}
