<?php

namespace App\Livewire\Layouts;

use Livewire\Component;

class Sidebar extends Component
{
    public bool $open = true;

    public function toggleSidebar(): void
    {
        $this->open = ! $this->open;
    }

    public function render()
    {
        return view('livewire.layouts.sidebar');
    }
}