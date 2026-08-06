<?php

namespace App\Livewire\Configuracion\RespuestasRapidas;

use App\Models\RespuestaRapida;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public bool   $modal      = false;
    public ?int   $editandoId = null;
    public string $atajo      = '';
    public string $texto      = '';
    public int    $orden      = 0;
    public bool   $activa     = true;

    /** @var mixed archivo nuevo a subir (pdf/imagen) */
    public $adjunto;
    public ?string $adjuntoActualNombre = null;   // adjunto ya guardado (al editar)
    public ?string $adjuntoActualTipo   = null;
    public bool $quitarAdjunto = false;

    public string $busqueda = '';

    protected function rules(): array
    {
        return [
            'atajo'   => 'nullable|string|max:40',
            'texto'   => 'nullable|string|max:2000',
            'orden'   => 'integer|min:0|max:9999',
            'activa'  => 'boolean',
            // PDF o imagen, hasta 90MB (WhatsApp: img 5MB, doc 100MB)
            'adjunto' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:92160',
        ];
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'atajo', 'texto', 'orden', 'activa', 'adjunto', 'adjuntoActualNombre', 'adjuntoActualTipo', 'quitarAdjunto']);
        $this->activa = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $r = RespuestaRapida::findOrFail($id);
        $this->editandoId = $r->id;
        $this->atajo      = (string) $r->atajo;
        $this->texto      = (string) $r->texto;
        $this->orden      = (int) $r->orden;
        $this->activa     = (bool) $r->activa;
        $this->adjunto    = null;
        $this->quitarAdjunto = false;
        $this->adjuntoActualNombre = $r->adjunto_nombre;
        $this->adjuntoActualTipo   = $r->adjunto_tipo;
        $this->modal = true;
    }

    public function cerrarModal(): void
    {
        $this->modal = false;
    }

    public function guardar(): void
    {
        $this->validate();

        // Debe tener al menos texto o un adjunto.
        $tendraAdjunto = $this->adjunto
            || ($this->editandoId && $this->adjuntoActualNombre && !$this->quitarAdjunto);
        if (trim($this->texto) === '' && !$tendraAdjunto) {
            $this->addError('texto', 'Escribe un texto o adjunta un archivo.');
            return;
        }

        $data = [
            'atajo'  => trim($this->atajo) ?: null,
            'texto'  => $this->texto,
            'orden'  => $this->orden,
            'activa' => $this->activa,
        ];

        $registro = $this->editandoId ? RespuestaRapida::findOrFail($this->editandoId) : null;

        // Quitar adjunto existente si se pidió (o si se sube uno nuevo que lo reemplaza).
        if ($registro && ($this->quitarAdjunto || $this->adjunto) && $registro->adjunto_path) {
            Storage::disk('public')->delete($registro->adjunto_path);
            $data = array_merge($data, ['adjunto_path' => null, 'adjunto_nombre' => null, 'adjunto_mime' => null, 'adjunto_tipo' => null]);
        }

        // Guardar nuevo adjunto.
        if ($this->adjunto) {
            $mime = $this->adjunto->getMimeType();
            $esImagen = str_starts_with((string) $mime, 'image/');
            $ext  = strtolower($this->adjunto->getClientOriginalExtension() ?: ($esImagen ? 'jpg' : 'pdf'));
            $path = $this->adjunto->storeAs('respuestas-rapidas', 'rr_' . uniqid() . '.' . $ext, 'public');
            $data = array_merge($data, [
                'adjunto_path'   => $path,
                'adjunto_nombre' => $this->adjunto->getClientOriginalName(),
                'adjunto_mime'   => $mime,
                'adjunto_tipo'   => $esImagen ? 'image' : 'document',
            ]);
        }

        if ($registro) {
            $registro->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Respuesta actualizada']);
        } else {
            RespuestaRapida::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Respuesta creada']);
        }

        $this->reset(['adjunto', 'quitarAdjunto']);
        $this->modal = false;
    }

    public function eliminar(int $id): void
    {
        RespuestaRapida::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Eliminada']);
    }

    public function toggleActiva(int $id): void
    {
        $r = RespuestaRapida::findOrFail($id);
        $r->update(['activa' => !$r->activa]);
    }

    public function render()
    {
        $items = RespuestaRapida::query()
            ->when($this->busqueda, fn($q) => $q->where(fn($qq) => $qq
                ->where('atajo', 'like', "%{$this->busqueda}%")
                ->orWhere('texto', 'like', "%{$this->busqueda}%")))
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return view('livewire.configuracion.respuestas-rapidas.index', compact('items'))
            ->layout('layouts.app');
    }
}
