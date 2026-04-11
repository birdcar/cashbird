<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\WorkOS\FGAService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FGAServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FGAService $fga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fga = app(FGAService::class);
    }

    public function test_create_warrant_sends_correct_request(): void
    {
        Http::fake([
            '*/fga/v1/warrants' => Http::response([], 200),
        ]);

        $this->fga->createWarrant('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.workos.com/fga/v1/warrants'
                && $request['resource_type'] === 'budget_category'
                && $request['resource_id'] === 'cat-123'
                && $request['relation'] === 'viewer'
                && $request['subject']['resource_type'] === 'user'
                && $request['subject']['resource_id'] === 'user_01ABC';
        });
    }

    public function test_delete_warrant_sends_correct_request(): void
    {
        Http::fake([
            '*/fga/v1/warrants' => Http::response([], 200),
        ]);

        $this->fga->deleteWarrant('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/fga/v1/warrants');
        });
    }

    public function test_check_returns_true_when_authorized(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => true]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        $this->assertTrue($result);
    }

    public function test_check_returns_false_when_unauthorized(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => false]],
            ], 200),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        $this->assertFalse($result);
    }

    public function test_check_returns_false_on_api_failure(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([], 500),
        ]);

        $result = $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        $this->assertFalse($result);
    }

    public function test_check_caches_result(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => true]],
            ], 200),
        ]);

        $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');
        $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        Http::assertSentCount(1);
    }

    public function test_create_warrant_invalidates_cache(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::response([
                'results' => [['authorized' => true]],
            ], 200),
            '*/fga/v1/warrants' => Http::response([], 200),
        ]);

        // First check: hits HTTP (cached under gen 0)
        $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');
        // Create warrant: bumps generation counter
        $this->fga->createWarrant('budget_category', 'cat-123', 'editor', 'user', 'user_01ABC');
        // Second check: gen changed, old cache key misses, hits HTTP again
        $this->fga->check('budget_category', 'cat-123', 'viewer', 'user', 'user_01ABC');

        // 1 check + 1 warrant + 1 check = 3 HTTP requests
        Http::assertSentCount(3);
    }

    public function test_list_warrants_returns_collection(): void
    {
        Http::fake([
            '*/fga/v1/warrants*' => Http::response([
                'data' => [
                    ['resource_type' => 'budget_category', 'resource_id' => 'cat-123', 'relation' => 'viewer'],
                ],
            ], 200),
        ]);

        $warrants = $this->fga->listWarrants('budget_category', 'cat-123');

        $this->assertCount(1, $warrants);
        $this->assertEquals('viewer', $warrants[0]['relation']);
    }

    public function test_batch_check_returns_keyed_results(): void
    {
        Http::fake([
            '*/fga/v1/check' => Http::sequence()
                ->push(['results' => [['authorized' => true]]], 200)
                ->push(['results' => [['authorized' => false]]], 200),
        ]);

        $results = $this->fga->batchCheck([
            ['resource_type' => 'budget_category', 'resource_id' => 'cat-1', 'relation' => 'viewer', 'subject_type' => 'user', 'subject_id' => 'u1'],
            ['resource_type' => 'budget_category', 'resource_id' => 'cat-2', 'relation' => 'viewer', 'subject_type' => 'user', 'subject_id' => 'u1'],
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results['budget_category:cat-1:viewer:user:u1']);
        $this->assertFalse($results['budget_category:cat-2:viewer:user:u1']);
    }
}
