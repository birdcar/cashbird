<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\NetWorthCard;
use App\Livewire\NetWorth\NetWorthDashboard;
use App\Models\Account;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class NetWorthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_worth_page_requires_authentication(): void
    {
        $response = $this->get('/net-worth');

        $response->assertRedirect();
    }

    public function test_net_worth_page_renders_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NetWorthDashboard::class)
            ->assertSee('Connect a bank account to start tracking your net worth.');
    }

    public function test_net_worth_page_shows_account_balances(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Main Checking',
            'balance_current' => 500000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthDashboard::class)
            ->assertSee('Main Checking')
            ->assertSee('5,000.00');
    }

    public function test_net_worth_page_shows_month_over_month_change(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'balance_current' => 500000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01',
            'net_worth' => 400000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-04-01',
            'net_worth' => 500000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthDashboard::class)
            ->assertSee('1,000.00')
            ->assertSee('from last month');
    }

    public function test_net_worth_page_shows_trend_chart(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'balance_current' => 500000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01',
            'net_worth' => 400000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-04-01',
            'net_worth' => 500000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthDashboard::class)
            ->assertSee('Net worth over time');
    }

    public function test_dashboard_card_shows_net_worth(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'balance_current' => 300000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthCard::class)
            ->assertSee('3,000.00')
            ->assertSee('View details');
    }

    public function test_dashboard_card_shows_positive_change_in_sage(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'balance_current' => 300000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01',
            'net_worth' => 200000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-04-01',
            'net_worth' => 300000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthCard::class)
            ->assertSeeHtml('text-sage-600');
    }

    public function test_dashboard_card_shows_negative_change_in_terracotta(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'balance_current' => 200000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01',
            'net_worth' => 300000,
        ]);
        NetWorthSnapshot::factory()->create([
            'user_id' => $user->id,
            'month' => '2026-04-01',
            'net_worth' => 200000,
        ]);

        Livewire::actingAs($user)
            ->test(NetWorthCard::class)
            ->assertSeeHtml('text-terracotta-600');
    }

    public function test_net_worth_route_is_named(): void
    {
        $this->assertTrue(
            Route::has('net-worth.index'),
            'Net worth route should be named'
        );
    }

    public function test_sidebar_contains_net_worth_link(): void
    {
        $sidebarContent = file_get_contents(
            resource_path('views/livewire/layout/sidebar.blade.php')
        );

        $this->assertStringContainsString('Net Worth', $sidebarContent);
        $this->assertStringContainsString('net-worth.index', $sidebarContent);
        $this->assertStringContainsString('phosphor-chart-line', $sidebarContent);
    }
}
