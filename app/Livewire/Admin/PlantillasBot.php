<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\BotPromptService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PlantillasBot extends Component
{
    public string $tipoSeleccionado = 'restaurante';
    public bool $verPlantillaCompleta = false;

    /**
     * Mapeo de tipo → metadatos visuales.
     */
    public function getTiposMetaProperty(): array
    {
        return [
            'restaurante'  => ['emoji' => '🍽️', 'color' => 'orange', 'desc' => 'Comida, domicilios, combos'],
            'cafeteria'    => ['emoji' => '☕', 'color' => 'amber',  'desc' => 'Café de especialidad, bebidas'],
            'carniceria'   => ['emoji' => '🥩', 'color' => 'red',    'desc' => 'Cortes, asado, sancocho'],
            'panaderia'    => ['emoji' => '🥐', 'color' => 'yellow', 'desc' => 'Pan, repostería, eventos'],
            'tienda'       => ['emoji' => '🛒', 'color' => 'blue',   'desc' => 'Minimarket, abarrotes'],
            'ferreteria'   => ['emoji' => '🔧', 'color' => 'gray',   'desc' => 'Herramientas, materiales'],
            'distribuidora'=> ['emoji' => '📦', 'color' => 'purple', 'desc' => 'Mayorista, volumen'],
            'farmacia'     => ['emoji' => '💊', 'color' => 'emerald','desc' => 'Droguería, OTC'],
            'servicios'    => ['emoji' => '🛠️', 'color' => 'cyan',   'desc' => 'Profesionales, agenda'],
            'manufactura'  => ['emoji' => '🏭', 'color' => 'slate',  'desc' => 'Producción, especificaciones'],
        ];
    }

    public function render()
    {
        $bloques = BotPromptService::todosLosBloquesEspecializados();
        $plantillaMaestra = BotPromptService::plantillaGenerica();
        $bloqueSeleccionado = $bloques[$this->tipoSeleccionado] ?? '';

        // Stats
        $charsMaestra = mb_strlen($plantillaMaestra);
        $tokensMaestra = (int) ceil($charsMaestra / 4);

        return view('livewire.admin.plantillas-bot', [
            'bloques' => $bloques,
            'plantillaMaestra' => $plantillaMaestra,
            'bloqueSeleccionado' => $bloqueSeleccionado,
            'tiposMeta' => $this->tiposMeta,
            'charsMaestra' => $charsMaestra,
            'tokensMaestra' => $tokensMaestra,
            'tenants' => Tenant::orderBy('nombre')->get(['id', 'nombre', 'slug', 'tipo_negocio']),
        ]);
    }
}
