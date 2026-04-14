<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TransactionsSynced;
use App\Jobs\SyncAccountTransactions;
use App\Jobs\SyncAllAccounts;
use App\Models\Account;
use App\Models\Connection;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Stripe\Exception\AuthenticationException;
use Stripe\FinancialConnections\Account as StripeAccount;
use Stripe\FinancialConnections\Transaction as StripeTransaction;
use Tests\TestCase;

class TransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Connection $connection;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TransactionsSynced::class]);

        $this->user = User::factory()->create();
        $institution = Institution::factory()->create();
        $this->connection = Connection::factory()->create([
            'user_id' => $this->user->id,
            'institution_id' => $institution->id,
        ]);
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'connection_id' => $this->connection->id,
            'institution_id' => $institution->id,
            'external_id' => 'fca_test_001',
        ]);
    }

    public function test_full_sync_creates_transactions_and_updates_balances(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')
                ->with('fca_test_001')
                ->andReturn(['current' => 150000, 'available' => 140000, 'type' => 'cash']);

            $mock->shouldReceive('listTransactions')
                ->with('fca_test_001', null)
                ->andReturn(collect([
                    StripeTransaction::constructFrom([
                        'id' => 'fctxn_001',
                        'object' => 'financial_connections.transaction',
                        'amount' => -1050,
                        'description' => 'Coffee Shop',
                        'status' => 'posted',
                        'transacted_at' => '2026-04-01',
                    ]),
                    StripeTransaction::constructFrom([
                        'id' => 'fctxn_002',
                        'object' => 'financial_connections.transaction',
                        'amount' => -2500,
                        'description' => 'Grocery Store',
                        'status' => 'posted',
                        'transacted_at' => '2026-04-02',
                    ]),
                ]));
        });

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(StripeFinancialConnectionsClient::class));

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'external_id' => 'fctxn_001',
            'amount' => -1050,
            'description' => 'Coffee Shop',
        ]);

        $this->account->refresh();
        $this->assertEquals(150000, $this->account->balance_current);
        $this->assertEquals(140000, $this->account->balance_available);
        $this->assertNotNull($this->account->last_synced_at);
    }

    public function test_sync_dispatches_transactions_synced_event(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')->andReturn(['current' => 100, 'available' => 100, 'type' => 'cash']);
            $mock->shouldReceive('listTransactions')->andReturn(collect([]));
        });

        $job = new SyncAccountTransactions($this->account);
        $job->handle(app(StripeFinancialConnectionsClient::class));

        Event::assertDispatched(TransactionsSynced::class, fn ($e) => $e->account->id === $this->account->id);
    }

    public function test_sync_with_zero_transactions(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')->andReturn(['current' => 50000, 'available' => 50000, 'type' => 'cash']);
            $mock->shouldReceive('listTransactions')->andReturn(collect([]));
        });

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(StripeFinancialConnectionsClient::class));

        $this->assertDatabaseCount('transactions', 0);
        $this->assertNotNull($this->account->refresh()->last_synced_at);
    }

    public function test_upserts_existing_transactions(): void
    {
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'external_id' => 'fctxn_001',
            'amount' => -1050,
            'status' => 'pending',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')->andReturn(['current' => 50000, 'available' => 50000, 'type' => 'cash']);
            $mock->shouldReceive('listTransactions')
                ->andReturn(collect([
                    StripeTransaction::constructFrom([
                        'id' => 'fctxn_001',
                        'object' => 'financial_connections.transaction',
                        'amount' => -1050,
                        'description' => 'Coffee Shop',
                        'status' => 'posted',
                        'transacted_at' => '2026-04-01',
                    ]),
                ]));
        });

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(StripeFinancialConnectionsClient::class));

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'external_id' => 'fctxn_001',
            'status' => 'posted',
        ]);
    }

    public function test_marks_connection_expired_on_auth_error(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')
                ->andThrow(AuthenticationException::factory('Unauthorized', 401));
        });

        $job = new SyncAccountTransactions($this->account);

        try {
            $job->handle(app(StripeFinancialConnectionsClient::class));
        } catch (\Throwable) {
            // Expected — job marks connection expired then fails
        }

        $this->assertEquals('expired', $this->connection->refresh()->status);
    }

    public function test_sync_all_accounts_dispatches_per_account_jobs(): void
    {
        Queue::fake();

        $stripeAccount = StripeAccount::constructFrom([
            'id' => 'fca_test_001',
            'object' => 'financial_connections.account',
            'display_name' => 'Checking',
            'institution_name' => 'Chase',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) use ($stripeAccount) {
            $mock->shouldReceive('getAccount')
                ->with('fca_test_001')
                ->andReturn($stripeAccount);
        });

        $job = new SyncAllAccounts($this->user);
        $job->handle(app(StripeFinancialConnectionsClient::class));

        Queue::assertPushed(SyncAccountTransactions::class, 1);
    }
}
