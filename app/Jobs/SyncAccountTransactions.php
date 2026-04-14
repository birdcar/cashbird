<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\TransactionsSynced;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stripe\Exception\ApiErrorException;

class SyncAccountTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public Account $account,
        public bool $fullSync = false,
    ) {}

    public function handle(StripeFinancialConnectionsClient $client): void
    {
        $this->syncBalances($client);
        $this->syncTransactions($client);

        $this->account->update(['last_synced_at' => now()]);

        TransactionsSynced::dispatch($this->account);
    }

    private function syncBalances(StripeFinancialConnectionsClient $client): void
    {
        try {
            $balances = $client->getBalances($this->account->external_id);

            $this->account->update([
                'balance_current' => $balances['current'],
                'balance_available' => $balances['available'],
            ]);
        } catch (ApiErrorException $e) {
            if ($e->getHttpStatus() === 401 || $e->getHttpStatus() === 403) {
                $this->account->loadMissing('connection');
                $this->account->connection?->update(['status' => 'expired']);
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    private function syncTransactions(StripeFinancialConnectionsClient $client): void
    {
        $startingAfter = null;

        if (! $this->fullSync) {
            $latest = $this->account->transactions()
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
            $startingAfter = $latest?->external_id;
        }

        do {
            $transactions = $client->listTransactions(
                $this->account->external_id,
                $startingAfter,
            );

            foreach ($transactions as $txn) {
                Transaction::updateOrCreate(
                    ['external_id' => $txn->id],
                    [
                        'account_id' => $this->account->id,
                        'user_id' => $this->account->user_id,
                        'amount' => $txn->amount,
                        'date' => $txn->transacted_at ?? $txn->posted_at ?? now()->toDateString(),
                        'description' => $txn->description,
                        'merchant_name' => $txn->description,
                        'status' => $txn->status,
                        'type' => null,
                        'running_balance' => null,
                        'raw_data' => $txn->toArray(),
                    ],
                );
            }

            $startingAfter = $transactions->isNotEmpty() ? $transactions->last()->id : null;
        } while ($transactions->isNotEmpty() && $transactions->count() >= 100);
    }
}
