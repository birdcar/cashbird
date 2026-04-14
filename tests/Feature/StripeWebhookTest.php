<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncAccountTransactions;
use App\Models\Account;
use App\Models\Connection;
use App\Services\Stripe\StripeFinancialConnectionsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['stripe.webhook_secret' => $this->webhookSecret]);
    }

    private function signPayload(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }

    private function makeEvent(string $type, array $objectData): string
    {
        return json_encode([
            'id' => 'evt_test_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => $objectData,
            ],
        ]);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $payload = $this->makeEvent('financial_connections.account.disconnected', ['id' => 'fca_123']);

        $response = $this->postJson(route('stripe.webhook'), [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    public function test_missing_signature_returns_400(): void
    {
        $payload = $this->makeEvent('financial_connections.account.disconnected', ['id' => 'fca_123']);

        $response = $this->call('POST', route('stripe.webhook'), content: $payload);

        $response->assertStatus(400);
    }

    public function test_refreshed_transactions_dispatches_sync_job(): void
    {
        Queue::fake();

        $connection = Connection::factory()->create();
        $account = Account::factory()->create([
            'external_id' => 'fca_123',
            'connection_id' => $connection->id,
        ]);

        $payload = $this->makeEvent('financial_connections.account.refreshed_transactions_data', [
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
        ]);

        $response = $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
        Queue::assertPushed(SyncAccountTransactions::class, fn ($job) => $job->account->id === $account->id);
    }

    public function test_refreshed_balance_updates_account(): void
    {
        $connection = Connection::factory()->create();
        $account = Account::factory()->create([
            'external_id' => 'fca_456',
            'connection_id' => $connection->id,
            'balance_current' => 0,
            'balance_available' => 0,
        ]);

        $this->mock(StripeFinancialConnectionsClient::class, function ($mock) {
            $mock->shouldReceive('getBalances')
                ->with('fca_456')
                ->andReturn(['current' => 150000, 'available' => 140000, 'type' => 'cash']);
        });

        $payload = $this->makeEvent('financial_connections.account.refreshed_balance', [
            'id' => 'fca_456',
            'object' => 'financial_connections.account',
        ]);

        $response = $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);

        $account->refresh();
        $this->assertEquals(150000, $account->balance_current);
        $this->assertEquals(140000, $account->balance_available);
    }

    public function test_disconnected_marks_connection(): void
    {
        $connection = Connection::factory()->create(['status' => 'active']);
        Account::factory()->create([
            'external_id' => 'fca_789',
            'connection_id' => $connection->id,
        ]);

        $payload = $this->makeEvent('financial_connections.account.disconnected', [
            'id' => 'fca_789',
            'object' => 'financial_connections.account',
        ]);

        $response = $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);

        $connection->refresh();
        $this->assertEquals('disconnected', $connection->status);
    }

    public function test_unknown_event_returns_200(): void
    {
        $payload = $this->makeEvent('some.unknown.event', ['id' => 'obj_123']);

        $response = $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signPayload($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }
}
