<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Livewire\Savings\CreateGoal;
use App\Livewire\Savings\SavingsGoalsList;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsGoalsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_page_requires_authentication(): void
    {
        $response = $this->get('/savings');

        $response->assertRedirect();
    }

    public function test_savings_page_renders_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SavingsGoalsList::class)
            ->assertSee('No savings goals yet');
    }

    public function test_savings_page_shows_goals_with_progress(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Vacation Fund',
            'target_amount' => 200000,
            'current_balance' => 100000,
            'monthly_contribution' => 25000,
            'status' => GoalStatus::Active,
            'priority' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(SavingsGoalsList::class)
            ->assertSee('Vacation Fund')
            ->assertSee('1,000.00')
            ->assertSee('2,000.00')
            ->assertSee('50%');
    }

    public function test_savings_page_shows_stage_indicator(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SavingsGoalsList::class)
            ->assertSee('Building full emergency fund');
    }

    public function test_savings_page_goals_ordered_by_priority(): void
    {
        $user = User::factory()->create();
        SavingsGoal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Low Priority Goal',
            'status' => GoalStatus::Active,
            'priority' => 5,
        ]);
        SavingsGoal::factory()->create([
            'user_id' => $user->id,
            'name' => 'High Priority Goal',
            'status' => GoalStatus::Active,
            'priority' => 0,
        ]);

        $component = Livewire::actingAs($user)
            ->test(SavingsGoalsList::class);

        $html = $component->html();
        $highPos = strpos($html, 'High Priority Goal');
        $lowPos = strpos($html, 'Low Priority Goal');
        $this->assertLessThan($lowPos, $highPos);
    }

    public function test_create_goal_form_renders(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateGoal::class)
            ->assertSee('Goal name')
            ->assertSee('Target amount')
            ->assertSee('Save Goal');
    }

    public function test_create_goal_saves_and_redirects(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateGoal::class)
            ->set('name', 'New Car')
            ->set('target_amount', '15000')
            ->set('monthly_contribution', '500')
            ->call('save')
            ->assertRedirect(route('savings.index'));

        $this->assertDatabaseHas('savings_goals', [
            'user_id' => $user->id,
            'name' => 'New Car',
            'target_amount' => 1500000,
            'monthly_contribution' => 50000,
        ]);
    }

    public function test_savings_routes_are_named(): void
    {
        $this->assertTrue(Route::has('savings.index'));
        $this->assertTrue(Route::has('savings.create'));
    }

    public function test_sidebar_contains_savings_link(): void
    {
        $sidebarContent = file_get_contents(
            resource_path('views/livewire/layout/sidebar.blade.php')
        );

        $this->assertStringContainsString('Savings', $sidebarContent);
        $this->assertStringContainsString('savings.index', $sidebarContent);
        $this->assertStringContainsString('phosphor-piggy-bank', $sidebarContent);
    }
}
