<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TransactionsSynced;
use App\Jobs\SyncAccountTransactions;
use App\Jobs\SyncAllAccounts;
use App\Models\Account;
use App\Models\Institution;
use App\Models\TellerEnrollment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Institution $institution;
    private TellerEnrollment $enrollment;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TransactionsSynced::class]);

        $this->user = User::factory()->create();
        $this->institution = Institution::factory()->create();
        $this->enrollment = TellerEnrollment::factory()->create([
            'user_id' => $this->user->id,
            'institution_id' => $this->institution->id,
            'access_token' => 'test_token',
        ]);
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'enrollment_id' => $this->enrollment->id,
            'institution_id' => $this->institution->id,
            'teller_id' => 'acc_test_001',
        ]);
    }

    public function test_full_sync_fetches_all_transactions(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_test_001/balances' => Http::response([
                'account_id' => 'acc_test_001',
                'available' => '1500.00',
                'ledger' => '1500.00',
            ]),
            'api.teller.io/accounts/acc_test_001/transactions*' => Http::response([
                [
                    'id' => 'txn_001',
                    'amount' => '-10.50',
                    'date' => '2026-04-01',
                    'description' => 'Coffee Shop',
                    'status' => 'posted',
                    'type' => 'card_payment',
                    'details' => ['counterparty' => ['name' => 'Starbucks']],
                    'running_balance' => '1489.50',
                ],
                [
                    'id' => 'txn_002',
                    'amount' => '-25.00',
                    'date' => '2026-04-02',
                    'description' => 'Grocery Store',
                    'status' => 'posted',
                    'type' => 'card_payment',
                    'details' => ['counterparty' => ['name' => 'Whole Foods']],
                    'running_balance' => '1464.50',
                ],
            ]),
        ]);

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(\App\Services\Teller\TellerClient::class));

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'teller_id' => 'txn_001',
            'amount' => -1050,
            'description' => 'Coffee Shop',
            'merchant_name' => 'Starbucks',
        ]);

        $this->account->refresh();
        $this->assertEquals(150000, $this->account->balance_available);
        $this->assertEquals(150000, $this->account->balance_current);
        $this->assertNotNull($this->account->last_synced_at);
    }

    public function test_sync_with_zero_transactions(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_test_001/balances' => Http::response([
                'account_id' => 'acc_test_001',
                'available' => '500.00',
                'ledger' => '500.00',
            ]),
            'api.teller.io/accounts/acc_test_001/transactions*' => Http::response([]),
        ]);

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(\App\Services\Teller\TellerClient::class));

        $this->assertDatabaseCount('transactions', 0);
        $this->assertNotNull($this->account->refresh()->last_synced_at);
    }

    public function test_upserts_existing_transactions(): void
    {
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'teller_id' => 'txn_001',
            'amount' => -1050,
            'description' => 'Coffee Shop',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.teller.io/accounts/acc_test_001/balances' => Http::response([
                'available' => '500.00',
                'ledger' => '500.00',
            ]),
            'api.teller.io/accounts/acc_test_001/transactions*' => Http::response([
                [
                    'id' => 'txn_001',
                    'amount' => '-10.50',
                    'date' => '2026-04-01',
                    'description' => 'Coffee Shop',
                    'status' => 'posted',
                    'type' => 'card_payment',
                    'details' => ['counterparty' => ['name' => 'Starbucks']],
                ],
            ]),
        ]);

        $job = new SyncAccountTransactions($this->account, fullSync: true);
        $job->handle(app(\App\Services\Teller\TellerClient::class));

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'teller_id' => 'txn_001',
            'status' => 'posted',
        ]);
    }

    public function test_marks_enrollment_expired_on_401(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_test_001/balances' => Http::response(
                ['error' => 'unauthorized'],
                401,
            ),
        ]);

        $job = new SyncAccountTransactions($this->account, fullSync: true);

        try {
            $job->handle(app(\App\Services\Teller\TellerClient::class));
        } catch (\Throwable) {
            // Expected — job marks enrollment expired then fails
        }

        $this->assertEquals('expired', $this->enrollment->refresh()->status);
    }

    public function test_sync_all_accounts_dispatches_per_account_jobs(): void
    {
        Queue::fake();

        Http::fake([
            'api.teller.io/accounts' => Http::response([
                ['id' => 'acc_new_1', 'name' => 'Checking', 'type' => 'checking'],
                ['id' => 'acc_new_2', 'name' => 'Savings', 'type' => 'savings'],
            ]),
        ]);

        $job = new SyncAllAccounts($this->user);
        $job->handle(app(\App\Services\Teller\TellerClient::class));

        $this->assertDatabaseCount('accounts', 3); // 1 existing + 2 new
        Queue::assertPushed(SyncAccountTransactions::class, 2);
    }
}
