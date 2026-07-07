<?php

namespace App\Livewire\Sap;

use Livewire\Component;

/**
 * Vista full-page del chat IA + SAP (ruta /asistente-sap). La lógica vive en
 * el trait WithSapChat (compartida con el widget flotante).
 */
class Chat extends Component
{
    use WithSapChat;

    public function render()
    {
        return view('livewire.sap.chat')->layout('layouts.app');
    }
}
