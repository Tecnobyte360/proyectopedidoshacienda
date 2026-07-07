<?php

namespace App\Livewire\Sap;

use App\Models\SapTenantConfig;
use App\Services\TenantManager;
use Livewire\Component;

/**
 * Widget flotante (esquina) del asistente IA + SAP. Se inyecta en el layout
 * del panel (layouts.app) y solo se muestra si el tenant actual tiene agentes
 * SAP activos.
 */
class ChatWidget extends Component
{
    use WithSapChat;

    public bool $activo = false;
    public bool $abierto = false;

    public function mount(): void
    {
        $tid = app(TenantManager::class)->id();
        $cfg = $tid ? SapTenantConfig::where('tenant_id', $tid)->where('activo', true)->first() : null;
        $this->activo = $cfg && count($cfg->agentesActivos()) > 0;
    }

    public function render()
    {
        return view('livewire.sap.chat-widget');
    }
}
