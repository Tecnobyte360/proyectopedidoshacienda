<?php

namespace App\Livewire\EstadosWhatsapp;

use App\Services\WhatsappStatusService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;

class Index extends Component
{
    use WithFileUploads;

    public array $estados     = [];
    public array $conexiones  = [];
    public bool  $cargando    = true;
    public bool  $modal       = false;
    public bool  $cargandoAccion = false;

    // Form
    public string  $whatsappId   = '';
    public string  $body         = '';
    public $media                = null;   // Livewire file upload
    public ?string $scheduledFor = null;

    // Confirmación eliminar
    public bool $modalEliminar     = false;
    public ?int $estadoIdEliminar  = null;

    protected function rules(): array
    {
        return [
            'whatsappId' => 'required|integer',
            'body'       => 'nullable|string|max:2000',
            'media'      => 'nullable|file|max:16384', // 16MB max
        ];
    }

    public function mount(): void
    {
        $this->cargarDatos();
    }

    public function cargarDatos(): void
    {
        $this->cargando = true;

        try {
            $service = app(WhatsappStatusService::class);
            $this->estados    = $service->listar() ?: [];
            $this->conexiones = $service->conexionesDisponibles();
        } catch (\Throwable $e) {
            Log::error('EstadosWhatsapp mount: ' . $e->getMessage());
            $this->estados    = [];
            $this->conexiones = [];
        }

        $this->cargando = false;
    }

    public function abrirModal(): void
    {
        $this->reset(['whatsappId', 'body', 'media', 'scheduledFor']);

        // Pre-seleccionar primera conexión si solo hay una
        if (count($this->conexiones) === 1) {
            $this->whatsappId = (string) $this->conexiones[0]['id'];
        }

        $this->modal = true;
    }

    public function cerrarModal(): void
    {
        $this->modal = false;
        $this->reset(['whatsappId', 'body', 'media', 'scheduledFor']);
    }

    public function crearEstado(): void
    {
        if (empty($this->whatsappId)) {
            $this->dispatch('notify', type: 'error', message: 'Selecciona una conexión');
            return;
        }

        if (empty($this->body) && !$this->media) {
            $this->dispatch('notify', type: 'error', message: 'Agrega un texto o un archivo');
            return;
        }

        $this->cargandoAccion = true;

        try {
            $mediaPath = null;
            if ($this->media) {
                $mediaPath = $this->media->getRealPath();
            }

            $scheduled = null;
            if ($this->scheduledFor) {
                $scheduled = (new \DateTime($this->scheduledFor))->format('c');
            }

            $result = app(WhatsappStatusService::class)->crear(
                whatsappId: (int) $this->whatsappId,
                body: $this->body ?: null,
                mediaPath: $mediaPath,
                scheduledFor: $scheduled
            );

            if ($result) {
                $this->dispatch('notify', type: 'success', message: $scheduled
                    ? 'Estado programado correctamente'
                    : 'Estado publicado correctamente');
                $this->cerrarModal();
                $this->cargarDatos();
            } else {
                $this->dispatch('notify', type: 'error', message: 'Error al crear el estado. Revisa la conexión.');
            }
        } catch (\Throwable $e) {
            Log::error('EstadosWhatsapp crear: ' . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: 'Error: ' . $e->getMessage());
        }

        $this->cargandoAccion = false;
    }

    public function confirmarEliminar(int $id): void
    {
        $this->estadoIdEliminar = $id;
        $this->modalEliminar    = true;
    }

    public function cancelarEliminar(): void
    {
        $this->estadoIdEliminar = null;
        $this->modalEliminar    = false;
    }

    public function eliminarEstado(): void
    {
        if (!$this->estadoIdEliminar) return;

        $this->cargandoAccion = true;

        try {
            $ok = app(WhatsappStatusService::class)->eliminar($this->estadoIdEliminar);

            if ($ok) {
                $this->dispatch('notify', type: 'success', message: 'Estado eliminado');
                $this->cargarDatos();
            } else {
                $this->dispatch('notify', type: 'error', message: 'No se pudo eliminar el estado');
            }
        } catch (\Throwable $e) {
            Log::error('EstadosWhatsapp eliminar: ' . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: 'Error al eliminar');
        }

        $this->estadoIdEliminar = null;
        $this->modalEliminar    = false;
        $this->cargandoAccion   = false;
    }

    public function render()
    {
        return view('livewire.estados-whatsapp.index')
            ->layout('livewire.layouts.app');
    }
}
