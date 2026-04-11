<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Debt;
use App\Models\NetWorthSnapshot;
use Illuminate\Support\Collection;

class NetWorthCalculator
{
    /**
     * @return array{total_assets: int, total_debts: int, net_worth: int, breakdown: array{accounts: list<array{name: string, type: string, balance: int}>, debts: list<array{name: string, type: string, balance: int}>}}
     */
    public function compute(int $userId): array
    {
        $accounts = Account::where('user_id', $userId)->get();

        // Only count manually-added debts (account_id IS NULL) to avoid
        // double-counting debts already reflected in account balances.
        $debts = Debt::where('user_id', $userId)
            ->active()
            ->whereNull('account_id')
            ->get();

        $totalAssets = $accounts->sum('balance_current');
        $totalDebts = $debts->sum('current_balance');
        $netWorth = $totalAssets - $totalDebts;

        return [
            'total_assets' => $totalAssets,
            'total_debts' => $totalDebts,
            'net_worth' => $netWorth,
            'breakdown' => [
                'accounts' => $accounts->map(fn (Account $a) => [
                    'name' => $a->name,
                    'type' => $a->type,
                    'balance' => $a->balance_current,
                ])->toArray(),
                'debts' => $debts->map(fn (Debt $d) => [
                    'name' => $d->name,
                    'type' => $d->type->value,
                    'balance' => $d->current_balance,
                ])->toArray(),
            ],
        ];
    }

    /**
     * Get the most recent N months of snapshots in ascending order for chart display.
     * Uses orderByDesc + PHP re-sort to get the *most recent* N months
     * (a naive orderBy('month')->take(N) would return the oldest N).
     *
     * @return Collection<int, NetWorthSnapshot>
     */
    public function trend(int $userId, int $months = 12): Collection
    {
        return NetWorthSnapshot::where('user_id', $userId)
            ->orderByDesc('month')
            ->take($months)
            ->get()
            ->sortBy('month')
            ->values();
    }

    public function monthOverMonthChange(int $userId): ?int
    {
        $snapshots = NetWorthSnapshot::where('user_id', $userId)
            ->orderByDesc('month')
            ->take(2)
            ->get();

        if ($snapshots->count() < 2) {
            return null;
        }

        return $snapshots->first()->net_worth - $snapshots->last()->net_worth;
    }
}
