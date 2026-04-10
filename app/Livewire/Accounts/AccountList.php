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
            ->join('institutions', 'accounts.institution_id', '=', 'institutions.id')
            ->orderBy('institutions.name')
            ->select('accounts.*')
            ->get();

        return view('livewire.accounts.account-list', [
            'accounts' => $accounts,
        ]);
    }
}
