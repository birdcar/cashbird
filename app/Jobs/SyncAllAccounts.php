<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class SyncAllAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public User $user,
    ) {}

    public function handle(StripeFinancialConnectionsClient $client): void
    {
        $connections = $this->user->connections()->where('status', 'active')->get();

        foreach ($connections as $connection) {
            foreach ($connection->accounts as $account) {
                try {
                    $stripeAccount = $client->getAccount($account->external_id);

                    $account->update([
                        'name' => $stripeAccount->display_name ?? $stripeAccount->institution_name,
                    ]);

                    SyncAccountTransactions::dispatch($account);
                } catch (ApiErrorException $e) {
                    Log::warning("Failed to sync account {$account->external_id}: {$e->getMessage()}");
                }
            }
        }
    }
}
