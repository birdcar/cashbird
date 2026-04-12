<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncAllAccounts;
use App\Models\Account;
use App\Models\Connection;
use App\Models\Institution;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

class FinancialConnectionsController extends Controller
{
    public function createSession(Request $request, StripeFinancialConnectionsClient $client): JsonResponse
    {
        try {
            $session = $client->createSession(
                ['balances', 'transactions', 'ownership'],
                route('accounts.index'),
            );
        } catch (ApiErrorException $e) {
            report($e);

            return response()->json(['error' => 'Failed to create connection session.'], 500);
        }

        return response()->json(['client_secret' => $session->client_secret]);
    }

    public function store(Request $request, StripeFinancialConnectionsClient $client): RedirectResponse
    {
        $request->validate([
            'stripe_account_ids' => 'required|array|min:1',
            'stripe_account_ids.*' => 'required|string',
        ]);

        $user = $request->user();
        assert($user !== null);

        try {
            DB::transaction(function () use ($request, $client, $user) {
                foreach ($request->input('stripe_account_ids') as $accountId) {
                    $stripeAccount = $client->getAccount($accountId);

                    // Stripe FC provides institution_name but no institution ID;
                    // use the name as the external identifier
                    $institutionName = $stripeAccount->institution_name;
                    $institution = Institution::firstOrCreate(
                        ['external_id' => $institutionName],
                        ['name' => $institutionName],
                    );

                    $connection = Connection::firstOrCreate(
                        ['user_id' => $user->id, 'institution_id' => $institution->id],
                        ['stripe_account_id' => $accountId, 'connected_at' => now()],
                    );

                    Account::updateOrCreate(
                        ['external_id' => $accountId],
                        [
                            'user_id' => $user->id,
                            'connection_id' => $connection->id,
                            'institution_id' => $institution->id,
                            'name' => $stripeAccount->display_name ?? $stripeAccount->institution_name,
                            'type' => $this->mapAccountType($stripeAccount->subcategory ?? $stripeAccount->category ?? 'other'),
                            'last_synced_at' => null,
                        ],
                    );

                    $client->subscribeToTransactions($accountId);
                    $client->subscribeToBalances($accountId);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('accounts.index')
                ->with('error', 'This institution is already connected.');
        }

        SyncAllAccounts::dispatch($user);

        return redirect()->route('accounts.index')
            ->with('success', 'Account connected. Syncing transactions...');
    }

    private function mapAccountType(string $stripeCategory): string
    {
        return match ($stripeCategory) {
            'checking' => 'checking',
            'savings' => 'savings',
            'credit_card' => 'credit_card',
            'mortgage', 'line_of_credit', 'student', 'auto' => 'loan',
            default => 'other',
        };
    }
}
