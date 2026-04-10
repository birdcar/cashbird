<?php

declare(strict_types=1);

namespace App\Services\Teller;

use App\Jobs\SyncAccountTransactions;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TellerWebhookHandler
{
    public function handle(Request $request): void
    {
        $this->verifySignature($request);

        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;

        match ($type) {
            'transactions.processed' => $this->handleTransactionsProcessed($payload),
            default => Log::info("Unhandled Teller webhook type: {$type}"),
        };
    }

    private function verifySignature(Request $request): void
    {
        $secret = config('teller.signing_secret');
        $signature = $request->header('Teller-Signature');

        if (! $secret || ! $signature) {
            abort(403, 'Invalid webhook signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid webhook signature');
        }
    }

    private function handleTransactionsProcessed(array $payload): void
    {
        $accountId = $payload['data']['account_id'] ?? null;

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
