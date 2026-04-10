<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\TransactionsSynced;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Teller\TellerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAccountTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [1, 5, 30];

    public function __construct(
        public Account $account,
        public bool $fullSync = false,
    ) {}

    public function handle(TellerClient $teller): void
    {
        $enrollment = $this->account->enrollment;
        $accessToken = $enrollment->getDecryptedAccessToken();

        $this->syncBalances($teller, $accessToken);
        $this->syncTransactions($teller, $accessToken);

        $this->account->update(['last_synced_at' => now()]);

        TransactionsSynced::dispatch($this->account);
    }

    private function syncBalances(TellerClient $teller, string $accessToken): void
    {
        try {
            $balances = $teller->getAccountBalances($accessToken, $this->account->teller_id);

            $this->account->update([
                'balance_available' => $this->toCents($balances['available'] ?? null),
                'balance_current' => $this->toCents($balances['ledger'] ?? null),
            ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() === 401) {
                $this->account->enrollment->update(['status' => 'expired']);
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    private function syncTransactions(TellerClient $teller, string $accessToken): void
    {
        $fromId = null;

        if (! $this->fullSync) {
            $fromId = $this->account->transactions()->max('teller_id');
        }

        do {
            $transactions = $teller->listTransactions(
                $accessToken,
                $this->account->teller_id,
                $fromId,
            );

            foreach ($transactions as $txn) {
                Transaction::updateOrCreate(
                    ['teller_id' => $txn['id']],
                    [
                        'account_id' => $this->account->id,
                        'user_id' => $this->account->user_id,
                        'amount' => $this->toCents($txn['amount']),
                        'date' => $txn['date'],
                        'description' => $txn['description'],
                        'merchant_name' => $txn['details']['counterparty']['name'] ?? null,
                        'status' => $txn['status'],
                        'type' => $txn['type'] ?? null,
                        'running_balance' => $this->toCents($txn['running_balance'] ?? null),
                        'raw_data' => $txn,
                    ],
                );
            }

            $fromId = $transactions->isNotEmpty() ? $transactions->last()['id'] : null;
        } while ($transactions->isNotEmpty() && $transactions->count() >= 100);
    }

    private function toCents(?string $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        return (int) round((float) $amount * 100);
    }
}
