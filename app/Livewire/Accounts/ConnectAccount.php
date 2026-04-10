<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use Illuminate\View\View;
use Livewire\Component;

class ConnectAccount extends Component
{
    public function render(): View
    {
        return view('livewire.accounts.connect-account', [
            'appId' => config('teller.app_id'),
        ]);
    }
}
