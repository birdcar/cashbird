<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Teller\TellerClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TellerClientTest extends TestCase
{
    private TellerClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new TellerClient(
            certPath: null,
            keyPath: null,
            baseUrl: 'https://api.teller.io',
        );
    }

    public function test_list_accounts_returns_collection(): void
    {
        Http::fake([
            'api.teller.io/accounts' => Http::response([
                ['id' => 'acc_001', 'name' => 'Checking', 'type' => 'depository'],
                ['id' => 'acc_002', 'name' => 'Savings', 'type' => 'depository'],
            ]),
        ]);

        $accounts = $this->client->listAccounts('test_token');

        $this->assertCount(2, $accounts);
        $this->assertEquals('acc_001', $accounts[0]['id']);
    }

    public function test_list_accounts_empty(): void
    {
        Http::fake([
            'api.teller.io/accounts' => Http::response([]),
        ]);

        $accounts = $this->client->listAccounts('test_token');

        $this->assertCount(0, $accounts);
    }

    public function test_get_account_returns_array(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_001' => Http::response([
                'id' => 'acc_001',
                'name' => 'Checking',
                'type' => 'depository',
            ]),
        ]);

        $account = $this->client->getAccount('test_token', 'acc_001');

        $this->assertEquals('acc_001', $account['id']);
        $this->assertEquals('Checking', $account['name']);
    }

    public function test_get_account_balances(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_001/balances' => Http::response([
                'account_id' => 'acc_001',
                'available' => '1234.56',
                'ledger' => '1234.56',
            ]),
        ]);

        $balances = $this->client->getAccountBalances('test_token', 'acc_001');

        $this->assertEquals('acc_001', $balances['account_id']);
        $this->assertEquals('1234.56', $balances['available']);
    }

    public function test_list_transactions_without_cursor(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_001/transactions*' => Http::response([
                ['id' => 'txn_001', 'amount' => '-10.50', 'description' => 'Coffee'],
                ['id' => 'txn_002', 'amount' => '-25.00', 'description' => 'Lunch'],
            ]),
        ]);

        $transactions = $this->client->listTransactions('test_token', 'acc_001');

        $this->assertCount(2, $transactions);
    }

    public function test_list_transactions_with_cursor(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_001/transactions*' => Http::response([
                ['id' => 'txn_003', 'amount' => '-5.00', 'description' => 'Snack'],
            ]),
        ]);

        $transactions = $this->client->listTransactions('test_token', 'acc_001', 'txn_002');

        $this->assertCount(1, $transactions);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'from_id=txn_002');
        });
    }

    public function test_list_transactions_empty(): void
    {
        Http::fake([
            'api.teller.io/accounts/acc_001/transactions*' => Http::response([]),
        ]);

        $transactions = $this->client->listTransactions('test_token', 'acc_001');

        $this->assertCount(0, $transactions);
    }

    public function test_throws_on_401_unauthorized(): void
    {
        Http::fake([
            'api.teller.io/accounts' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $this->client->listAccounts('expired_token');
    }

    public function test_throws_on_500_server_error(): void
    {
        Http::fake([
            'api.teller.io/accounts' => Http::response(['error' => 'internal'], 500),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $this->client->listAccounts('test_token');
    }

    public function test_get_identity(): void
    {
        Http::fake([
            'api.teller.io/identity' => Http::response([
                'id' => 'id_001',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]),
        ]);

        $identity = $this->client->getIdentity('test_token');

        $this->assertEquals('John', $identity['first_name']);
    }
}
