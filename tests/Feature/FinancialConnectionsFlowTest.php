<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncAllAccounts;
use App\Models\Connection;
use App\Models\Institution;
use App\Models\User;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Stripe\Exception\AuthenticationException;
use Stripe\FinancialConnections\Account as StripeAccount;
use Stripe\FinancialConnections\Session;
use Tests\TestCase;

class FinancialConnectionsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_session_returns_json_with_client_secret(): void
    {
        $session = Session::constructFrom([
            'id' => 'fcsess_123',
            'object' => 'financial_connections.session',
            'client_secret' => 'fcsess_secret_abc',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) use ($session) {
            $mock->shouldReceive('createSession')
                ->once()
                ->with(['balances', 'transactions', 'ownership'], Mockery::type('string'))
                ->andReturn($session);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->postJson(route('connections.session'));

        $response->assertOk()
            ->assertJson(['client_secret' => 'fcsess_secret_abc']);
    }

    public function test_create_session_requires_authentication(): void
    {
        $response = $this->postJson(route('connections.session'));

        $response->assertUnauthorized();
    }

    public function test_create_session_returns_error_json_when_stripe_fails(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('createSession')
                ->once()
                ->andThrow(new AuthenticationException('Invalid API key'));
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->postJson(route('connections.session'));

        $response->assertStatus(500)
            ->assertJson(['error' => 'Failed to create connection session.']);
    }

    public function test_store_creates_institution_connection_and_account(): void
    {
        Queue::fake();

        $stripeAccount = StripeAccount::constructFrom([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
            'institution_id' => 'inst_chase',
            'institution_name' => 'Chase',
            'display_name' => 'Chase Checking',
            'category' => 'cash',
            'subcategory' => 'checking',
            'status' => 'active',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) use ($stripeAccount) {
            $mock->shouldReceive('getAccount')->once()->with('fca_123')->andReturn($stripeAccount);
            $mock->shouldReceive('subscribeToTransactions')->once()->with('fca_123');
            $mock->shouldReceive('subscribeToBalances')->once()->with('fca_123');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('connections.store'), [
                'stripe_account_ids' => ['fca_123'],
            ]);

        $response->assertRedirect(route('accounts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('institutions', [
            'external_id' => 'Chase',
            'name' => 'Chase',
        ]);

        $this->assertDatabaseCount('connections', 1);

        $connection = Connection::first();
        $this->assertEquals($user->id, $connection->user_id);
        $this->assertEquals('fca_123', $connection->stripe_account_id);

        $this->assertDatabaseHas('accounts', [
            'external_id' => 'fca_123',
            'name' => 'Chase Checking',
            'type' => 'checking',
        ]);

        Queue::assertPushed(SyncAllAccounts::class);
    }

    public function test_store_handles_multiple_account_ids(): void
    {
        Queue::fake();

        $account1 = StripeAccount::constructFrom([
            'id' => 'fca_1',
            'object' => 'financial_connections.account',
            'institution_id' => 'inst_chase',
            'institution_name' => 'Chase',
            'display_name' => 'Chase Checking',
            'subcategory' => 'checking',
        ]);

        $account2 = StripeAccount::constructFrom([
            'id' => 'fca_2',
            'object' => 'financial_connections.account',
            'institution_id' => 'inst_chase',
            'institution_name' => 'Chase',
            'display_name' => 'Chase Savings',
            'subcategory' => 'savings',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) use ($account1, $account2) {
            $mock->shouldReceive('getAccount')->with('fca_1')->andReturn($account1);
            $mock->shouldReceive('getAccount')->with('fca_2')->andReturn($account2);
            $mock->shouldReceive('subscribeToTransactions')->twice();
            $mock->shouldReceive('subscribeToBalances')->twice();
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('connections.store'), [
                'stripe_account_ids' => ['fca_1', 'fca_2'],
            ]);

        $response->assertRedirect(route('accounts.index'));

        // One institution, one connection, two accounts
        $this->assertDatabaseCount('institutions', 1);
        $this->assertDatabaseCount('connections', 1);
        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_store_reuses_existing_connection_for_same_institution(): void
    {
        Queue::fake();

        $institution = Institution::factory()->create(['external_id' => 'Chase']);
        $user = User::factory()->create();
        $existing = Connection::factory()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'stripe_account_id' => 'fca_existing',
        ]);

        $stripeAccount = StripeAccount::constructFrom([
            'id' => 'fca_new',
            'object' => 'financial_connections.account',
            'institution_id' => 'inst_chase',
            'institution_name' => 'Chase',
            'display_name' => 'Chase Checking',
            'subcategory' => 'checking',
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) use ($stripeAccount) {
            $mock->shouldReceive('getAccount')->andReturn($stripeAccount);
            $mock->shouldReceive('subscribeToTransactions');
            $mock->shouldReceive('subscribeToBalances');
        });

        $response = $this->actingAs($user, 'workos')
            ->post(route('connections.store'), [
                'stripe_account_ids' => ['fca_new'],
            ]);

        $response->assertRedirect(route('accounts.index'));
        $response->assertSessionHas('success');

        // Connection reused, not duplicated
        $this->assertDatabaseCount('connections', 1);
        // Account still created under existing connection
        $this->assertDatabaseHas('accounts', ['external_id' => 'fca_new', 'connection_id' => $existing->id]);

        Queue::assertPushed(SyncAllAccounts::class);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('connections.store'), [
            'stripe_account_ids' => ['fca_123'],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('connections.store'), []);

        $response->assertSessionHasErrors(['stripe_account_ids']);
    }

    public function test_store_validates_account_ids_are_strings(): void
    {
        $this->mock(StripeFinancialConnectionsClient::class);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'workos')
            ->post(route('connections.store'), [
                'stripe_account_ids' => [123, null],
            ]);

        $response->assertSessionHasErrors(['stripe_account_ids.0', 'stripe_account_ids.1']);
    }
}
