<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncAccountTransactions;
use App\Models\Account;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                config('stripe.webhook_secret', ''),
            );
        } catch (SignatureVerificationException) {
            return response('Invalid signature.', 400);
        }

        $accountId = $event->data->object->id ?? null;

        match ($event->type) {
            'financial_connections.account.refreshed_transactions_data' => $this->handleTransactionsRefreshed($accountId),
            'financial_connections.account.refreshed_balance' => $this->handleBalanceRefreshed($accountId),
            'financial_connections.account.disconnected' => $this->handleDisconnected($accountId),
            default => null,
        };

        return response('', 200);
    }

    private function handleTransactionsRefreshed(?string $accountId): void
    {
        if ($accountId === null) {
            return;
        }

        $account = Account::where('external_id', $accountId)->first();

        if ($account) {
            SyncAccountTransactions::dispatch($account);
        }
    }

    private function handleBalanceRefreshed(?string $accountId): void
    {
        if ($accountId === null) {
            return;
        }

        $account = Account::where('external_id', $accountId)->first();

        if (! $account) {
            return;
        }

        $client = app(StripeFinancialConnectionsClient::class);
        $balances = $client->getBalances($accountId);
        $account->update([
            'balance_current' => $balances['current'],
            'balance_available' => $balances['available'],
            'last_synced_at' => now(),
        ]);
    }

    private function handleDisconnected(?string $accountId): void
    {
        if ($accountId === null) {
            return;
        }

        $account = Account::where('external_id', $accountId)->first();
        $connection = $account?->connection;

        if ($connection) {
            $connection->update(['status' => 'disconnected']);
            Log::warning("Financial connection disconnected: account {$accountId}");
        }
    }
}
