<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Categorization\SpendingAggregator;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendingAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private SpendingAggregator $aggregator;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->aggregator = new SpendingAggregator;
        $this->user = User::factory()->create();
    }

    public function test_computes_period_aggregation(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();
        $start = Carbon::parse('2026-03-01');
        $end = Carbon::parse('2026-03-31');

        Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -5000,
            'date' => '2026-03-15',
        ]);

        $results = $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);

        $this->assertNotEmpty($results);
        $found = collect($results)->firstWhere('category_id', $groceries->id);
        $this->assertEquals(-15000, $found['total_amount']);
        $this->assertEquals(3, $found['transaction_count']);
    }

    public function test_returns_cached_aggregation(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();
        $start = Carbon::parse('2026-03-01');
        $end = Carbon::parse('2026-03-31');

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -5000,
            'date' => '2026-03-15',
        ]);

        // First call computes and caches
        $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);

        // Add more transactions
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -3000,
            'date' => '2026-03-20',
        ]);

        // Second call returns cached (stale) data
        $results = $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);
        $found = collect($results)->firstWhere('category_id', $groceries->id);
        $this->assertEquals(-5000, $found['total_amount']);
    }

    public function test_invalidate_cache_forces_recompute(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();
        $start = Carbon::parse('2026-03-01');
        $end = Carbon::parse('2026-03-31');

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -5000,
            'date' => '2026-03-15',
        ]);

        $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -3000,
            'date' => '2026-03-20',
        ]);

        $this->aggregator->invalidateCache($this->user->id, Carbon::parse('2026-03-15'));

        $results = $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);
        $found = collect($results)->firstWhere('category_id', $groceries->id);
        $this->assertEquals(-8000, $found['total_amount']);
    }

    public function test_top_categories_returns_ranked_spending(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();
        $streaming = Category::where('name', 'Streaming')->first();
        $start = Carbon::parse('2026-03-01');
        $end = Carbon::parse('2026-03-31');

        Transaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'category_id' => $groceries->id,
            'amount' => -5000,
            'date' => '2026-03-15',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $streaming->id,
            'amount' => -1599,
            'date' => '2026-03-15',
        ]);

        $top = $this->aggregator->topCategories($this->user->id, $start, $end);

        $this->assertCount(2, $top);
        $this->assertEquals($groceries->id, $top[0]['category_id']);
        $this->assertEquals(15000, $top[0]['total_amount']);
    }

    public function test_month_over_month_with_gaps(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => -10000,
            'date' => '2026-02-15',
        ]);

        // March has no transactions (gap)

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'amount' => -5000,
            'date' => '2026-04-10',
        ]);

        $mom = $this->aggregator->monthOverMonth($this->user->id, 3);

        $this->assertCount(3, $mom);
        $this->assertEquals('2026-02', $mom[0]['month']);
        $this->assertEquals(10000, $mom[0]['total_amount']);
        $this->assertEquals('2026-03', $mom[1]['month']);
        $this->assertEquals(0, $mom[1]['total_amount']);
        $this->assertEquals('2026-04', $mom[2]['month']);
        $this->assertEquals(5000, $mom[2]['total_amount']);

        Carbon::setTestNow();
    }

    public function test_empty_transactions_returns_empty(): void
    {
        $start = Carbon::parse('2026-03-01');
        $end = Carbon::parse('2026-03-31');

        $results = $this->aggregator->forPeriod($this->user->id, 'monthly', $start, $end);

        $this->assertEmpty($results);
    }
}
