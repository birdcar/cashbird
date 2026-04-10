<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessTellerWebhook;
use App\Models\Account;
use App\Models\Institution;
use App\Models\TellerEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TellerWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $signingSecret = 'test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['teller.signing_secret' => $this->signingSecret]);
    }

    private function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->signingSecret);
    }

    public function test_valid_webhook_dispatches_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $institution = Institution::factory()->create();
        $enrollment = TellerEnrollment::factory()->create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
        ]);
        Account::factory()->create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'institution_id' => $institution->id,
            'teller_id' => 'acc_webhook_001',
        ]);

        $payload = json_encode([
            'type' => 'transactions.processed',
            'data' => ['account_id' => 'acc_webhook_001'],
        ]);

        $response = $this->postJson(
            route('teller.webhook'),
            json_decode($payload, true),
            ['Teller-Signature' => $this->signPayload($payload)],
        );

        $response->assertOk();
        Queue::assertPushed(ProcessTellerWebhook::class);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $payload = json_encode([
            'type' => 'transactions.processed',
            'data' => ['account_id' => 'acc_001'],
        ]);

        $response = $this->postJson(
            route('teller.webhook'),
            json_decode($payload, true),
            ['Teller-Signature' => 'invalid_signature'],
        );

        $response->assertForbidden();
    }

    public function test_missing_signature_returns_403(): void
    {
        $response = $this->postJson(
            route('teller.webhook'),
            ['type' => 'transactions.processed', 'data' => ['account_id' => 'acc_001']],
        );

        $response->assertForbidden();
    }

    public function test_unknown_event_type_returns_ok(): void
    {
        Queue::fake();

        $payload = json_encode([
            'type' => 'unknown.event',
            'data' => [],
        ]);

        $response = $this->postJson(
            route('teller.webhook'),
            json_decode($payload, true),
            ['Teller-Signature' => $this->signPayload($payload)],
        );

        $response->assertOk();
        Queue::assertPushed(ProcessTellerWebhook::class);
    }

    public function test_webhook_without_csrf_token(): void
    {
        $payload = json_encode(['type' => 'test', 'data' => []]);

        $response = $this->post(
            route('teller.webhook'),
            [],
            [
                'Content-Type' => 'application/json',
                'Teller-Signature' => $this->signPayload($payload),
            ],
        );

        // Should not get 419 (CSRF) — webhook route is excluded
        $this->assertNotEquals(419, $response->getStatusCode());
    }
}
