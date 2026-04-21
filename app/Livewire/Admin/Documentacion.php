<?php

namespace App\Livewire\Admin;

use App\Services\TenantManager;
use Livewire\Component;

class Documentacion extends Component
{
    public function mount(): void
    {
        // Esta página es SOLO para el super-admin de la plataforma.
        // Si entra desde un subdominio de tenant (la-hacienda.tecnobyte360.com),
        // no debe ver la documentación interna de la plataforma.
        if (app(TenantManager::class)->current() !== null) {
            abort(404);
        }
    }

    public function render()
    {
        return view('livewire.admin.documentacion')
            ->layout('layouts.app');
    }
}
