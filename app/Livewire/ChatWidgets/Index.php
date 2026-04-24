<?php

namespace App\Livewire\ChatWidgets;

use App\Models\ChatWidget;
use Livewire\Component;

class Index extends Component
{
    public bool $modal = false;
    public ?int $editandoId = null;

    public string $nombre           = '';
    public string $titulo           = '¿En qué te ayudamos?';
    public string $subtitulo        = '';
    public string $saludoInicial    = '¡Hola! 👋 ¿En qué te puedo ayudar hoy?';
    public string $placeholder      = 'Escribe un mensaje...';
    public string $colorPrimario    = '#d68643';
    public string $colorSecundario  = '#a85f24';
    public string $posicion         = 'bottom-right';
    public string $avatarUrl        = '';
    public string $dominiosPermitidos = '';
    public bool   $activo           = true;
    public bool   $pedirNombre      = true;
    public bool   $pedirTelefono    = false;
    public bool   $sonidoNotificacion = true;

    protected function rules(): array
    {
        return [
            'nombre'             => 'required|string|max:120',
            'titulo'             => 'required|string|max:120',
            'subtitulo'          => 'nullable|string|max:200',
            'saludoInicial'      => 'nullable|string|max:500',
            'placeholder'        => 'required|string|max:120',
            'colorPrimario'      => 'required|string|max:20',
            'colorSecundario'    => 'required|string|max:20',
            'posicion'           => 'required|in:bottom-right,bottom-left',
            'avatarUrl'          => 'nullable|string|max:500',
            'dominiosPermitidos' => 'nullable|string|max:1000',
        ];
    }

    public function render()
    {
        return view('livewire.chat-widgets.index', [
            'widgets' => ChatWidget::orderByDesc('id')->get(),
        ])->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->reset(['editandoId', 'nombre', 'subtitulo', 'avatarUrl', 'dominiosPermitidos']);
        $this->titulo = '¿En qué te ayudamos?';
        $this->saludoInicial = '¡Hola! 👋 ¿En qué te puedo ayudar hoy?';
        $this->placeholder = 'Escribe un mensaje...';
        $this->colorPrimario = '#d68643';
        $this->colorSecundario = '#a85f24';
        $this->posicion = 'bottom-right';
        $this->activo = true;
        $this->pedirNombre = true;
        $this->pedirTelefono = false;
        $this->sonidoNotificacion = true;
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $w = ChatWidget::findOrFail($id);
        $this->editandoId         = $w->id;
        $this->nombre             = $w->nombre;
        $this->titulo             = $w->titulo;
        $this->subtitulo          = (string) $w->subtitulo;
        $this->saludoInicial      = (string) $w->saludo_inicial;
        $this->placeholder        = $w->placeholder;
        $this->colorPrimario      = $w->color_primario;
        $this->colorSecundario    = $w->color_secundario;
        $this->posicion           = $w->posicion;
        $this->avatarUrl          = (string) $w->avatar_url;
        $this->dominiosPermitidos = (string) $w->dominios_permitidos;
        $this->activo             = $w->activo;
        $this->pedirNombre        = $w->pedir_nombre;
        $this->pedirTelefono      = $w->pedir_telefono;
        $this->sonidoNotificacion = $w->sonido_notificacion;
        $this->modal = true;
    }

    public function cerrarModal(): void { $this->modal = false; }

    public function guardar(): void
    {
        $this->validate();

        $data = [
            'nombre'              => $this->nombre,
            'titulo'              => $this->titulo,
            'subtitulo'           => $this->subtitulo ?: null,
            'saludo_inicial'      => $this->saludoInicial ?: null,
            'placeholder'         => $this->placeholder,
            'color_primario'      => $this->colorPrimario,
            'color_secundario'    => $this->colorSecundario,
            'posicion'            => $this->posicion,
            'avatar_url'          => $this->avatarUrl ?: null,
            'dominios_permitidos' => $this->dominiosPermitidos ?: null,
            'activo'              => $this->activo,
            'pedir_nombre'        => $this->pedirNombre,
            'pedir_telefono'      => $this->pedirTelefono,
            'sonido_notificacion' => $this->sonidoNotificacion,
        ];

        if ($this->editandoId) {
            ChatWidget::findOrFail($this->editandoId)->update($data);
        } else {
            ChatWidget::create($data);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Guardado']);
    }

    public function toggleActivo(int $id): void
    {
        $w = ChatWidget::findOrFail($id);
        $w->update(['activo' => !$w->activo]);
    }

    public function eliminar(int $id): void
    {
        ChatWidget::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Eliminado']);
    }
}
