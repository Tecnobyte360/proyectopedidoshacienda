<?php

namespace App\Livewire\Configuracion;

use App\Models\ConfiguracionBot;
use Livewire\Component;

class Bot extends Component
{
    public bool   $enviar_imagenes_productos = false;
    public int    $max_imagenes_por_mensaje  = 3;
    public bool   $enviar_imagen_destacados  = false;
    public bool   $saludar_con_promociones   = true;

    public string $modelo_openai             = 'gpt-4o-mini';
    public float  $temperatura               = 0.85;
    public int    $max_tokens                = 700;

    public string $nombre_asesora            = 'Sofía';
    public string $frase_bienvenida          = '';
    public bool   $activo                    = true;

    public array $modelosDisponibles = [
        'gpt-4o-mini' => 'GPT-4o mini (rápido, económico)',
        'gpt-4o'      => 'GPT-4o (más natural, más caro)',
        'gpt-4-turbo' => 'GPT-4 Turbo (potente)',
    ];

    public function mount(): void
    {
        $cfg = ConfiguracionBot::actual();

        $this->enviar_imagenes_productos = (bool) $cfg->enviar_imagenes_productos;
        $this->max_imagenes_por_mensaje  = (int) $cfg->max_imagenes_por_mensaje;
        $this->enviar_imagen_destacados  = (bool) $cfg->enviar_imagen_destacados;
        $this->saludar_con_promociones   = (bool) $cfg->saludar_con_promociones;
        $this->modelo_openai             = (string) ($cfg->modelo_openai ?? 'gpt-4o-mini');
        $this->temperatura               = (float) $cfg->temperatura;
        $this->max_tokens                = (int) $cfg->max_tokens;
        $this->nombre_asesora            = (string) ($cfg->nombre_asesora ?? 'Sofía');
        $this->frase_bienvenida          = (string) ($cfg->frase_bienvenida ?? '');
        $this->activo                    = (bool) $cfg->activo;
    }

    protected function rules(): array
    {
        return [
            'enviar_imagenes_productos' => 'boolean',
            'max_imagenes_por_mensaje'  => 'integer|min:1|max:10',
            'enviar_imagen_destacados'  => 'boolean',
            'saludar_con_promociones'   => 'boolean',
            'modelo_openai'             => 'required|string|max:60',
            'temperatura'               => 'numeric|min:0|max:2',
            'max_tokens'                => 'integer|min:100|max:4000',
            'nombre_asesora'            => 'required|string|max:60',
            'frase_bienvenida'          => 'nullable|string|max:500',
            'activo'                    => 'boolean',
        ];
    }

    public function guardar(): void
    {
        $data = $this->validate();

        $cfg = ConfiguracionBot::actual();
        $cfg->update($data);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Configuración del bot guardada.',
        ]);
    }

    public function render()
    {
        return view('livewire.configuracion.bot')->layout('layouts.app');
    }
}
