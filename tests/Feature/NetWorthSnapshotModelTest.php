<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetWorthSnapshotModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_record(): void
    {
        $snapshot = NetWorthSnapshot::factory()->create();

        $this->assertDatabaseHas('net_worth_snapshots', ['id' => $snapshot->id]);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $snapshot = NetWorthSnapshot::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($snapshot->user->is($user));
    }

    public function test_user_has_many_snapshots(): void
    {
        $user = User::factory()->create();
        foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $month) {
            NetWorthSnapshot::factory()->create(['user_id' => $user->id, 'month' => $month]);
        }

        $this->assertCount(3, $user->netWorthSnapshots);
    }

    public function test_unique_constraint_on_user_and_month(): void
    {
        $user = User::factory()->create();
        $month = '2026-04-01';

        NetWorthSnapshot::factory()->create(['user_id' => $user->id, 'month' => $month]);

        $this->expectException(QueryException::class);
        NetWorthSnapshot::factory()->create(['user_id' => $user->id, 'month' => $month]);
    }

    public function test_breakdown_is_cast_to_array(): void
    {
        $breakdown = [
            'accounts' => [['name' => 'Checking', 'balance' => 500000]],
            'debts' => [['name' => 'Credit Card', 'balance' => 200000]],
        ];

        $snapshot = NetWorthSnapshot::factory()->create(['breakdown' => $breakdown]);
        $snapshot->refresh();

        $this->assertIsArray($snapshot->breakdown);
        $this->assertEquals('Checking', $snapshot->breakdown['accounts'][0]['name']);
    }

    public function test_net_worth_can_be_negative(): void
    {
        $snapshot = NetWorthSnapshot::factory()->create([
            'total_assets' => 100000,
            'total_debts' => 500000,
            'net_worth' => -400000,
        ]);

        $this->assertEquals(-400000, $snapshot->net_worth);
    }
}
