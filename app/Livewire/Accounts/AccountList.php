<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use App\Jobs\SyncAllAccounts;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Accounts')]
class AccountList extends Component
{
    public function syncNow(): void
    {
        $user = auth()->user();
        assert($user !== null);

        SyncAllAccounts::dispatch($user);

        session()->flash('success', 'Sync started. Transactions will update shortly.');
    }

    public function render(): View
    {
        $user = auth()->user();
        assert($user !== null);

        $accounts = $user->accounts()
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
