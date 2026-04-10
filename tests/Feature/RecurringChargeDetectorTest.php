<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\RecurringChargeDetector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringChargeDetectorTest extends TestCase
{
    use RefreshDatabase;

    private RecurringChargeDetector $detector;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RecurringChargeDetector;
        $this->user = User::factory()->create();
    }

    public function test_detects_monthly_netflix_like_charges(): void
    {
        for ($i = 0; $i < 6; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'merchant_name' => 'NETFLIX',
                'amount' => -1599,
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDay(),
            ]);
        }

        $results = $this->detector->detect($this->user->id);

        $this->assertCount(1, $results);
        $charge = $results->first();
        $this->assertEquals('NETFLIX', $charge->merchant_name);
        $this->assertEquals('monthly', $charge->frequency);
        $this->assertGreaterThan(0.8, $charge->confidence);
        $this->assertEquals(1599, $charge->average_amount);
    }

    public function test_detects_variable_amount_recurring(): void
    {
        $amounts = [-8000, -9500, -11000, -8500, -10200, -9100];
        for ($i = 0; $i < 6; $i++) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'merchant_name' => 'ELECTRIC CO',
                'amount' => $amounts[$i],
                'date' => Carbon::now()->subMonths($i)->startOfMonth()->addDays(15),
            ]);
        }

        $results = $this->detector->detect($this->user->id);

        $this->assertCount(1, $results);
        $charge = $results->first();
        $this->assertEquals('monthly', $charge->frequency);
        $this->assertGreaterThanOrEqual(0.7, $charge->confidence);
    }

    public function test_ignores_one_off_merchants(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'merchant_name' => 'RANDOM STORE',
            'amount' => -5000,
            'date' => Carbon::now()->subMonth(),
        ]);

        $results = $this->detector->detect($this->user->id);

        $this->assertEmpty($results);
    }

    public function test_ignores_irregular_timing(): void
    {
        $dates = [1, 15, 45, 50, 120, 130];
        foreach ($dates as $daysAgo) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'merchant_name' => 'RANDOM SHOP',
                'amount' => -2000,
                'date' => Carbon::now()->subDays($daysAgo),
            ]);
        }

        $results = $this->detector->detect($this->user->id);

        $this->assertEmpty($results);
    }

    public function test_analyze_pattern_returns_null_under_three(): void
    {
        $transactions = collect([
            (object) ['date' => Carbon::now()->subMonths(2), 'amount' => -1599],
            (object) ['date' => Carbon::now()->subMonth(), 'amount' => -1599],
        ]);

        $pattern = $this->detector->analyzePattern('TEST', $transactions);

        $this->assertNull($pattern);
    }
}
