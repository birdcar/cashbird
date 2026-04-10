<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use Illuminate\View\View;
use Livewire\Component;

class AccountList extends Component
{
    public function render(): View
    {
        $accounts = auth()->user()->accounts()
            ->with('institution')
            ->orderBy('institution_id')
            ->get();

        return view('livewire.accounts.account-list', [
            'accounts' => $accounts,
        ]);
    }
}
