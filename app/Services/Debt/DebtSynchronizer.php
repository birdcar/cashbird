<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;

class DebtSynchronizer
{
    private const array DEBT_ACCOUNT_TYPES = ['credit_card', 'loan'];

    public function syncForUser(User $user): void
    {
        $debtAccounts = $user->accounts()
            ->whereIn('type', self::DEBT_ACCOUNT_TYPES)
            ->get();

        foreach ($debtAccounts as $account) {
            $this->syncAccount($user, $account);
        }
    }

    public function syncAccount(User $user, Account $account): void
    {
        $debt = Debt::where('account_id', $account->id)->first();

        if (! $debt) {
            $debt = $this->createDebtFromAccount($user, $account);
        } else {
            $this->updateBalance($debt, $account);
        }

        $this->detectPayments($debt, $account);
        $this->detectPayoff($debt);
    }

    private function createDebtFromAccount(User $user, Account $account): Debt
    {
        $balance = abs($account->balance_current);

        return Debt::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => $account->name,
            'type' => $account->type === 'credit_card' ? 'credit_card' : 'personal_loan',
            'lender' => $account->institution?->name,
            'current_balance' => $balance,
            'original_balance' => $balance,
            'apr' => 0.0,
            'minimum_payment' => 0,
            'status' => 'active',
        ]);
    }

    private function updateBalance(Debt $debt, Account $account): void
    {
        $newBalance = abs($account->balance_current);

        if ($newBalance !== $debt->current_balance) {
            $debt->update(['current_balance' => $newBalance]);
        }
    }

    private function detectPayments(Debt $debt, Account $account): void
    {
        $lastPayment = $debt->payments()->orderByDesc('payment_date')->first();
        $since = $lastPayment?->payment_date ?? $debt->created_at;

        $transactions = $account->transactions()
            ->where('amount', '>', 0)
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->get();

        foreach ($transactions as $transaction) {
            $existing = DebtPayment::where('transaction_id', $transaction->id)->exists();
            if ($existing) {
                continue;
            }

            DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => $transaction->amount,
                'balance_after' => $debt->current_balance,
                'payment_date' => $transaction->date,
                'source' => 'detected',
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    private function detectPayoff(Debt $debt): void
    {
        if ($debt->status !== 'active') {
            return;
        }

        if ($debt->current_balance <= 0) {
            $debt->update([
                'status' => 'paid_off',
                'current_balance' => 0,
                'paid_off_at' => now(),
            ]);
        }
    }
}
