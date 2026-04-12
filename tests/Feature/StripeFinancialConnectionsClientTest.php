<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Stripe\StripeFinancialConnectionsClient;
use Stripe\ApiRequestor;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeFinancialConnectionsClientTest extends TestCase
{
    private StripeFinancialConnectionsClient $client;

    /** @var array<int, array{0: string, 1: string, 2: array, 3: array, 4: bool}> */
    private array $requests = [];

    /** @var list<array{0: string, 1: int, 2: array}> */
    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests = [];
        $this->responses = [];

        $testCase = $this;
        $mockHttpClient = new class($testCase) implements ClientInterface
        {
            public function __construct(private readonly StripeFinancialConnectionsClientTest $test) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $this->test->recordRequest($method, $absUrl, $headers, $params, $hasFile);

                return $this->test->shiftResponse();
            }
        };

        ApiRequestor::setHttpClient($mockHttpClient);

        $this->client = new StripeFinancialConnectionsClient(secretKey: 'sk_test_fake');
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function recordRequest(string $method, string $absUrl, array $headers, array $params, bool $hasFile): void
    {
        $this->requests[] = [$method, $absUrl, $headers, $params, $hasFile];
    }

    /** @return array{0: string, 1: int, 2: array} */
    public function shiftResponse(): array
    {
        return array_shift($this->responses) ?? ['{}', 200, []];
    }

    private function enqueueResponse(array $body, int $status = 200): void
    {
        $this->responses[] = [json_encode($body), $status, []];
    }

    public function test_create_session_returns_session_with_client_secret(): void
    {
        $this->enqueueResponse([
            'id' => 'fcsess_123',
            'object' => 'financial_connections.session',
            'client_secret' => 'fcsess_secret_abc',
        ]);

        $result = $this->client->createSession(['balances', 'transactions'], 'https://example.com');

        $this->assertEquals('fcsess_secret_abc', $result->client_secret);
        $this->assertEquals('fcsess_123', $result->id);
        $this->assertStringContains('post', $this->requests[0][0]);
        $this->assertStringContains('/v1/financial_connections/sessions', $this->requests[0][1]);
    }

    public function test_get_account_returns_stripe_account(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
            'institution_name' => 'Chase',
            'status' => 'active',
        ]);

        $result = $this->client->getAccount('fca_123');

        $this->assertEquals('fca_123', $result->id);
        $this->assertEquals('Chase', $result->institution_name);
    }

    public function test_list_accounts_by_session(): void
    {
        $this->enqueueResponse([
            'object' => 'list',
            'data' => [
                ['id' => 'fca_1', 'object' => 'financial_connections.account'],
                ['id' => 'fca_2', 'object' => 'financial_connections.account'],
            ],
            'has_more' => false,
        ]);

        $result = $this->client->listAccountsBySession('fcsess_123');

        $this->assertCount(2, $result);
        $this->assertEquals('fca_1', $result[0]->id);
    }

    public function test_subscribe_to_transactions(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
        ]);

        $result = $this->client->subscribeToTransactions('fca_123');

        $this->assertEquals('fca_123', $result->id);
        $this->assertStringContains('/subscribe', $this->requests[0][1]);
    }

    public function test_subscribe_to_balances(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
        ]);

        $result = $this->client->subscribeToBalances('fca_123');

        $this->assertEquals('fca_123', $result->id);
        $this->assertStringContains('/subscribe', $this->requests[0][1]);
    }

    public function test_refresh_transactions(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
        ]);

        $result = $this->client->refreshTransactions('fca_123');

        $this->assertEquals('fca_123', $result->id);
        $this->assertStringContains('/refresh', $this->requests[0][1]);
    }

    public function test_list_transactions_without_cursor(): void
    {
        $this->enqueueResponse([
            'object' => 'list',
            'data' => [
                ['id' => 'fctxn_1', 'object' => 'financial_connections.transaction', 'amount' => -1050],
                ['id' => 'fctxn_2', 'object' => 'financial_connections.transaction', 'amount' => -2500],
            ],
            'has_more' => false,
        ]);

        $result = $this->client->listTransactions('fca_123');

        $this->assertCount(2, $result);
        $this->assertStringContains('/v1/financial_connections/transactions', $this->requests[0][1]);
    }

    public function test_list_transactions_with_cursor(): void
    {
        $this->enqueueResponse([
            'object' => 'list',
            'data' => [
                ['id' => 'fctxn_3', 'object' => 'financial_connections.transaction'],
            ],
            'has_more' => false,
        ]);

        $result = $this->client->listTransactions('fca_123', 'fctxn_2');

        $this->assertCount(1, $result);
        // starting_after is sent as a request param, not a URL query string
        $this->assertEquals('fctxn_2', $this->requests[0][3]['starting_after']);
    }

    public function test_list_transactions_empty(): void
    {
        $this->enqueueResponse([
            'object' => 'list',
            'data' => [],
            'has_more' => false,
        ]);

        $result = $this->client->listTransactions('fca_123');

        $this->assertCount(0, $result);
    }

    public function test_disconnect(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
            'status' => 'disconnected',
        ]);

        $result = $this->client->disconnect('fca_123');

        $this->assertEquals('disconnected', $result->status);
        $this->assertStringContains('/disconnect', $this->requests[0][1]);
    }

    public function test_get_balances_cash_account(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_123',
            'object' => 'financial_connections.account',
            'balance' => [
                'type' => 'cash',
                'cash' => [
                    'available' => ['usd' => 150000],
                    'current' => ['usd' => 160000],
                ],
                'credit' => null,
            ],
        ]);

        $result = $this->client->getBalances('fca_123');

        $this->assertEquals(160000, $result['current']);
        $this->assertEquals(150000, $result['available']);
        $this->assertEquals('cash', $result['type']);
    }

    public function test_get_balances_credit_account(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_456',
            'object' => 'financial_connections.account',
            'balance' => [
                'type' => 'credit',
                'cash' => null,
                'credit' => [
                    'used' => ['usd' => 75000],
                ],
            ],
        ]);

        $result = $this->client->getBalances('fca_456');

        $this->assertEquals(75000, $result['current']);
        $this->assertNull($result['available']);
        $this->assertEquals('credit', $result['type']);
    }

    public function test_get_balances_null_balance(): void
    {
        $this->enqueueResponse([
            'id' => 'fca_789',
            'object' => 'financial_connections.account',
            'balance' => null,
        ]);

        $result = $this->client->getBalances('fca_789');

        $this->assertNull($result['current']);
        $this->assertNull($result['available']);
        $this->assertEquals('unknown', $result['type']);
    }

    public function test_throws_on_authentication_error(): void
    {
        $this->enqueueResponse([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Invalid API Key provided',
            ],
        ], 401);

        $this->expectException(AuthenticationException::class);

        $this->client->getAccount('fca_123');
    }

    public function test_throws_on_rate_limit_error(): void
    {
        $this->enqueueResponse([
            'error' => [
                'type' => 'rate_limit_error',
                'message' => 'Too many requests',
            ],
        ], 429);

        $this->expectException(RateLimitException::class);

        $this->client->getAccount('fca_123');
    }

    public function test_throws_on_not_found_error(): void
    {
        $this->enqueueResponse([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'No such financial connections account: fca_missing',
                'code' => 'resource_missing',
            ],
        ], 404);

        $this->expectException(InvalidRequestException::class);

        $this->client->getAccount('fca_missing');
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
