<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class ApiDocs extends Component
{
    public function render()
    {
        return view('livewire.admin.api-docs')
            ->layout('layouts.app');
    }
}
