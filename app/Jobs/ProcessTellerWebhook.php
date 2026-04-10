<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTellerWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload,
    ) {}

    public function handle(): void
    {
        $type = $this->payload['type'] ?? null;

        match ($type) {
            'transactions.processed' => $this->handleTransactionsProcessed(),
            default => Log::info("Unhandled Teller webhook type: {$type}"),
        };
    }

    private function handleTransactionsProcessed(): void
    {
        $accountId = $this->payload['data']['account_id'] ?? null;

        if (! $accountId) {
            Log::warning('Teller webhook missing account_id in payload');

            return;
        }

        $account = Account::where('teller_id', $accountId)->first();

        if (! $account) {
            Log::warning("Teller webhook: account not found for teller_id {$accountId}");

            return;
        }

        SyncAccountTransactions::dispatch($account);
    }
}
