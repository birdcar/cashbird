<?php

declare(strict_types=1);

namespace App\Livewire\Accounts;

use App\Jobs\SyncAllAccounts;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Support\Facades\Log;
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

        $client = app(StripeFinancialConnectionsClient::class);

        foreach ($user->accounts as $account) {
            try {
                $client->refreshTransactions($account->external_id);
            } catch (\Exception $e) {
                Log::info("Refresh not available for {$account->external_id}: {$e->getMessage()}");
            }
        }

        SyncAllAccounts::dispatch($user);

        session()->flash('success', 'Sync requested. Transactions will update shortly.');
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
