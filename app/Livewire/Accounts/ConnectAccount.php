<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Connect Account')]
class ConnectAccount extends Component
{
    public function render(): View
    {
        return view('livewire.accounts.connect-account', [
            'stripePublishableKey' => config('stripe.publishable_key'),
        ]);
    }
}
