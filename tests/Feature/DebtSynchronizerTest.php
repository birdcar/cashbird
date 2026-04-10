<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Debt\DebtSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private DebtSynchronizer $synchronizer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->synchronizer = app(DebtSynchronizer::class);
        $this->user = User::factory()->create();
    }

    public function test_creates_debt_from_credit_card_account(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'name' => 'Chase Sapphire',
            'balance_current' => -250000,
        ]);

        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debts', 1);
        $debt = Debt::where('account_id', $account->id)->first();
        $this->assertEquals('Chase Sapphire', $debt->name);
        $this->assertEquals('credit_card', $debt->type);
        $this->assertEquals(250000, $debt->current_balance);
        $this->assertEquals('active', $debt->status);
    }

    public function test_creates_debt_from_loan_account(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'loan',
            'name' => 'Auto Loan',
            'balance_current' => -1500000,
        ]);

        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debts', 1);
        $debt = Debt::where('account_id', $account->id)->first();
        $this->assertEquals('personal_loan', $debt->type);
        $this->assertEquals(1500000, $debt->current_balance);
    }

    public function test_ignores_checking_and_savings_accounts(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'checking',
        ]);
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'savings',
        ]);

        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debts', 0);
    }

    public function test_updates_balance_on_subsequent_sync(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance_current' => -250000,
        ]);

        $this->synchronizer->syncForUser($this->user);

        $account->update(['balance_current' => -200000]);
        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debts', 1);
        $debt = Debt::where('account_id', $account->id)->first();
        $this->assertEquals(200000, $debt->current_balance);
    }

    public function test_does_not_create_duplicate_debt_for_same_account(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance_current' => -250000,
        ]);

        $this->synchronizer->syncForUser($this->user);
        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debts', 1);
    }

    public function test_detects_payments_from_transactions(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance_current' => -200000,
        ]);

        $this->synchronizer->syncForUser($this->user);
        $debt = Debt::where('account_id', $account->id)->first();

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => 50000,
            'date' => now()->addDay(),
        ]);

        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debt_payments', 1);
        $payment = DebtPayment::where('debt_id', $debt->id)->first();
        $this->assertEquals(50000, $payment->amount);
        $this->assertEquals('detected', $payment->source);
    }

    public function test_does_not_duplicate_payment_records(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance_current' => -200000,
        ]);

        $this->synchronizer->syncForUser($this->user);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => 50000,
            'date' => now()->addDay(),
        ]);

        $this->synchronizer->syncForUser($this->user);
        $this->synchronizer->syncForUser($this->user);

        $this->assertDatabaseCount('debt_payments', 1);
    }

    public function test_detects_payoff_when_balance_reaches_zero(): void
    {
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'credit_card',
            'balance_current' => 0,
        ]);

        $this->synchronizer->syncForUser($this->user);

        $debt = Debt::where('account_id', $account->id)->first();
        $this->assertEquals('paid_off', $debt->status);
        $this->assertNotNull($debt->paid_off_at);
        $this->assertEquals(0, $debt->current_balance);
    }
}
