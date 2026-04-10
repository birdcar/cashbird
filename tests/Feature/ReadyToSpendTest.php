<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\ReadyToSpend;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReadyToSpendTest extends TestCase
{
    use RefreshDatabase;

    private ReadyToSpend $rts;

    private User $user;

    private BudgetPeriod $period;

    private string $groceriesId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->rts = new ReadyToSpend;
        $this->user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $this->period = BudgetPeriod::factory()->create([
            'budget_id' => $budget->id,
            'month' => '2026-04-01',
            'total_income' => 500000,
            'status' => 'active',
        ]);

        $groceries = Category::where('name', 'Groceries')->first();
        $this->groceriesId = $groceries->id;

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 50000,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_computes_remaining_with_posted_and_pending(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->groceriesId,
            'amount' => -20000,
            'status' => 'posted',
            'date' => '2026-04-10',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->groceriesId,
            'amount' => -5000,
            'status' => 'pending',
            'date' => '2026-04-14',
        ]);

        $data = $this->rts->compute($this->user->id);

        $this->assertArrayHasKey($this->groceriesId, $data);
        $entry = $data[$this->groceriesId];

        $this->assertEquals(50000, $entry['allocated']);
        $this->assertEquals(20000, $entry['spent']);
        $this->assertEquals(5000, $entry['pending']);
        $this->assertEquals(25000, $entry['remaining']);
        $this->assertGreaterThan(0, $entry['daily_safe']);
    }

    public function test_no_transactions_means_full_allocation_available(): void
    {
        $data = $this->rts->compute($this->user->id);

        $entry = $data[$this->groceriesId];
        $this->assertEquals(50000, $entry['remaining']);
        $this->assertEquals(0, $entry['spent']);
        $this->assertEquals(0, $entry['pending']);
    }

    public function test_over_budget_shows_negative_remaining(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->groceriesId,
            'amount' => -60000,
            'status' => 'posted',
            'date' => '2026-04-10',
        ]);

        $data = $this->rts->compute($this->user->id);

        $this->assertEquals(-10000, $data[$this->groceriesId]['remaining']);
    }

    public function test_last_day_of_month_no_division_by_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-30'));

        $data = $this->rts->compute($this->user->id);

        $entry = $data[$this->groceriesId];
        $this->assertEquals(50000, $entry['daily_safe']);
    }

    public function test_returns_empty_when_no_budget(): void
    {
        $otherUser = User::factory()->create();

        $data = $this->rts->compute($otherUser->id);

        $this->assertEmpty($data);
    }

    public function test_publish_caches_data(): void
    {
        $this->rts->publish($this->user->id);

        $cached = Cache::get("cashbird:rts:{$this->user->id}");
        $this->assertNotNull($cached);
        $this->assertArrayHasKey($this->groceriesId, $cached);
    }

    public function test_daily_safe_to_spend_for_category(): void
    {
        $dailySafe = $this->rts->dailySafeToSpend($this->user->id, $this->groceriesId);

        $this->assertGreaterThan(0, $dailySafe);
    }
}
