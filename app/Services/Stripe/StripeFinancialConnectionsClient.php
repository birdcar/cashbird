<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use Illuminate\Support\Collection;
use Stripe\FinancialConnections\Account as StripeAccount;
use Stripe\FinancialConnections\Session;
use Stripe\FinancialConnections\Transaction as StripeTransaction;
use Stripe\StripeClient;

class StripeFinancialConnectionsClient
{
    private readonly StripeClient $stripe;

    public function __construct(
        string $secretKey,
        ?StripeClient $client = null,
    ) {
        $this->stripe = $client ?? new StripeClient([
            'api_key' => $secretKey,
            'max_network_retries' => 2,
        ]);
    }

    public function createSession(array $permissions, string $returnUrl): Session
    {
        return $this->stripe->financialConnections->sessions->create([
            'permissions' => $permissions,
            'filters' => [
                'countries' => ['US'],
            ],
            'return_url' => $returnUrl,
        ]);
    }

    public function getAccount(string $accountId): StripeAccount
    {
        return $this->stripe->financialConnections->accounts->retrieve($accountId);
    }

    /** @return Collection<int, StripeAccount> */
    public function listAccountsBySession(string $sessionId): Collection
    {
        $accounts = $this->stripe->financialConnections->accounts->all([
            'session' => $sessionId,
        ]);

        return collect($accounts->data);
    }

    /**
     * Fetch balance data for an FC account.
     *
     * Stripe balances differ by account type:
     * - Cash accounts (checking/savings): have `cash.available` and `cash.current` keyed by currency
     * - Credit accounts: have `credit.used` keyed by currency (no `available`)
     *
     * @return array{current: int|null, available: int|null, type: string}
     */
    public function getBalances(string $accountId): array
    {
        $account = $this->stripe->financialConnections->accounts->retrieve($accountId, [
            'expand' => ['balance'],
        ]);

        $balance = $account->balance;

        if ($balance === null) {
            return ['current' => null, 'available' => null, 'type' => 'unknown'];
        }

        $type = $balance->type ?? 'cash';

        if ($type === 'credit') {
            $used = $balance->credit->used['usd'] ?? null;

            return ['current' => $used, 'available' => null, 'type' => 'credit'];
        }

        return [
            'current' => $balance->cash->current['usd'] ?? null,
            'available' => $balance->cash->available['usd'] ?? null,
            'type' => 'cash',
        ];
    }

    public function subscribeToTransactions(string $accountId): StripeAccount
    {
        return $this->stripe->financialConnections->accounts->subscribe($accountId, [
            'features' => ['transactions'],
        ]);
    }

    public function subscribeToBalances(string $accountId): StripeAccount
    {
        return $this->stripe->financialConnections->accounts->subscribe($accountId, [
            'features' => ['balances'],
        ]);
    }

    /** The Stripe SDK returns the Account object with updated refresh status. */
    public function refreshTransactions(string $accountId): StripeAccount
    {
        return $this->stripe->financialConnections->accounts->refresh($accountId, [
            'features' => ['transactions'],
        ]);
    }

    /** @return Collection<int, StripeTransaction> */
    public function listTransactions(string $accountId, ?string $startingAfter = null): Collection
    {
        $params = [
            'account' => $accountId,
            'limit' => 100,
        ];

        if ($startingAfter !== null) {
            $params['starting_after'] = $startingAfter;
        }

        $transactions = $this->stripe->financialConnections->transactions->all($params);

        return collect($transactions->data);
    }

    public function disconnect(string $accountId): StripeAccount
    {
        return $this->stripe->financialConnections->accounts->disconnect($accountId);
    }
}
