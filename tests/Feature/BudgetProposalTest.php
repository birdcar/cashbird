<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateBudgetProposal;
use App\Livewire\Budget\ProposalReview;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\BudgetProposal;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BudgetPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->user = User::factory()->create();

        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $this->period = BudgetPeriod::factory()->create([
            'budget_id' => $budget->id,
            'total_income' => 500000,
            'status' => 'active',
        ]);
    }

    public function test_generates_proposal_for_overspent_category(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 30000,
            'spent_amount' => 40000,
        ]);

        $job = new GenerateBudgetProposal($this->user);
        $job->handle();

        $this->assertDatabaseCount('budget_proposals', 1);
        $proposal = BudgetProposal::first();
        $this->assertEquals('pending', $proposal->status);

        $changes = $proposal->changes;
        $this->assertNotEmpty($changes);
        $this->assertGreaterThan(30000, $changes[0]['new_amount']);
    }

    public function test_generates_proposal_for_underspent_category(): void
    {
        $restaurants = Category::where('name', 'Restaurants')->first();

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $restaurants->id,
            'allocated_amount' => 50000,
            'spent_amount' => 10000,
        ]);

        $job = new GenerateBudgetProposal($this->user);
        $job->handle();

        $this->assertDatabaseCount('budget_proposals', 1);
        $proposal = BudgetProposal::first();
        $changes = $proposal->changes;

        $this->assertLessThan(50000, $changes[0]['new_amount']);
    }

    public function test_skips_locked_allocations(): void
    {
        $rent = Category::where('name', 'Rent/Mortgage')->first();

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $rent->id,
            'allocated_amount' => 150000,
            'spent_amount' => 150000,
            'is_locked' => true,
        ]);

        $job = new GenerateBudgetProposal($this->user);
        $job->handle();

        $this->assertDatabaseCount('budget_proposals', 0);
    }

    public function test_no_proposal_when_spending_is_on_track(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 30000,
            'spent_amount' => 22000,
        ]);

        $job = new GenerateBudgetProposal($this->user);
        $job->handle();

        $this->assertDatabaseCount('budget_proposals', 0);
    }

    public function test_approve_proposal_updates_allocations(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();

        $allocation = BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 30000,
        ]);

        $proposal = BudgetProposal::factory()->create([
            'budget_period_id' => $this->period->id,
            'changes' => [[
                'category_id' => $groceries->id,
                'category_name' => 'Groceries',
                'old_amount' => 30000,
                'new_amount' => 40000,
                'rationale' => 'Increased spending',
            ]],
        ]);

        $this->actingAs($this->user, 'workos');

        $component = Livewire::test(ProposalReview::class, ['proposalId' => $proposal->id]);
        $component->call('approve');

        $allocation->refresh();
        $this->assertEquals(40000, $allocation->allocated_amount);

        $proposal->refresh();
        $this->assertEquals('approved', $proposal->status);
        $this->assertNotNull($proposal->reviewed_at);
    }

    public function test_reject_proposal_keeps_allocations(): void
    {
        $groceries = Category::where('name', 'Groceries')->first();

        BudgetAllocation::factory()->create([
            'budget_period_id' => $this->period->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 30000,
        ]);

        $proposal = BudgetProposal::factory()->create([
            'budget_period_id' => $this->period->id,
            'changes' => [[
                'category_id' => $groceries->id,
                'old_amount' => 30000,
                'new_amount' => 40000,
                'rationale' => 'Test',
            ]],
        ]);

        $this->actingAs($this->user, 'workos');

        $component = Livewire::test(ProposalReview::class, ['proposalId' => $proposal->id]);
        $component->call('reject');

        $proposal->refresh();
        $this->assertEquals('rejected', $proposal->status);
    }
}
