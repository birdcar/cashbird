<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use Livewire\Component;

class AppShell extends Component
{
    public function render()
    {
        return view('livewire.layout.app-shell')
            ->layout('components.layouts.app');
    }
}
