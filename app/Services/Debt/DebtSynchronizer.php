<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Enums\PaymentSource;
use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DebtSynchronizer
{
    private const array DEBT_ACCOUNT_TYPES = ['credit_card', 'loan'];

    public function syncForUser(User $user): void
    {
        $debtAccounts = $user->accounts()
            ->whereIn('type', self::DEBT_ACCOUNT_TYPES)
            ->with(['institution'])
            ->get();

        foreach ($debtAccounts as $account) {
            $this->syncAccount($user, $account);
        }
    }

    public function syncAccount(User $user, Account $account): void
    {
        DB::transaction(function () use ($user, $account) {
            $debt = Debt::where('account_id', $account->id)->first();

            if (! $debt) {
                $debt = $this->createDebtFromAccount($user, $account);
            } else {
                $this->updateBalance($debt, $account);
            }

            $this->detectPayments($debt, $account);
            $this->detectPayoff($debt);
        });
    }

    private function createDebtFromAccount(User $user, Account $account): Debt
    {
        $balance = abs($account->balance_current);

        return Debt::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => $account->name,
            'type' => $account->type === 'credit_card' ? DebtType::CreditCard : DebtType::PersonalLoan,
            'lender' => $account->institution?->name,
            'current_balance' => $balance,
            'original_balance' => $balance,
            'apr' => 0.0,
            'minimum_payment' => 0,
            'status' => DebtStatus::Active,
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

        if ($transactions->isEmpty()) {
            return;
        }

        $existingTransactionIds = DebtPayment::where('debt_id', $debt->id)
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->pluck('transaction_id')
            ->flip();

        foreach ($transactions as $transaction) {
            if ($existingTransactionIds->has($transaction->id)) {
                continue;
            }

            DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => $transaction->amount,
                'balance_after' => $debt->current_balance,
                'payment_date' => $transaction->date,
                'source' => PaymentSource::Detected,
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    private function detectPayoff(Debt $debt): void
    {
        if ($debt->status !== DebtStatus::Active) {
            return;
        }

        if ($debt->current_balance <= 0) {
            $debt->update([
                'status' => DebtStatus::PaidOff,
                'current_balance' => 0,
                'paid_off_at' => now(),
            ]);
        }
    }
}
