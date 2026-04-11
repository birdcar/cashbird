<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SnapshotNetWorth;
use App\Models\Account;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\Services\NetWorthCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotNetWorthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_creates_snapshot_for_all_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        $user1 = User::factory()->create();
        Account::factory()->create(['user_id' => $user1->id, 'balance_current' => 500000]);

        $user2 = User::factory()->create();
        Account::factory()->create(['user_id' => $user2->id, 'balance_current' => 300000]);

        (new SnapshotNetWorth)->handle(new NetWorthCalculator);

        $this->assertDatabaseHas('net_worth_snapshots', [
            'user_id' => $user1->id,
            'net_worth' => 500000,
        ]);
        $this->assertDatabaseHas('net_worth_snapshots', [
            'user_id' => $user2->id,
            'net_worth' => 300000,
        ]);
    }

    public function test_updates_existing_snapshot_in_same_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id, 'balance_current' => 500000]);

        // Use Carbon object for month so Eloquent serializes consistently
        $month = Carbon::parse('2026-04-01');
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => $month,
            'net_worth' => 400000,
            'total_assets' => 400000,
            'total_debts' => 0,
        ]);

        (new SnapshotNetWorth)->handle(new NetWorthCalculator);

        $snapshots = NetWorthSnapshot::where('user_id', $user->id)->get();
        $this->assertCount(1, $snapshots);
        $this->assertEquals(500000, $snapshots->first()->net_worth);
    }
}
