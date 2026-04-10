<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\BudgetAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_returns_structured_allocations(): void
    {
        BudgetAgent::fake([
            ['allocations' => json_encode([
                ['category_id' => 'cat_1', 'amount' => 15000, 'rationale' => 'Historical average for groceries'],
                ['category_id' => 'cat_2', 'amount' => 10000, 'rationale' => 'Reduced from last month'],
            ])],
        ]);

        $agent = BudgetAgent::make();
        $response = $agent->prompt('Allocate $250 discretionary pool across categories');

        $allocations = json_decode($response['allocations'], true);
        $this->assertCount(2, $allocations);
        $this->assertEquals(25000, collect($allocations)->sum('amount'));
    }

    public function test_agent_handles_zero_pool(): void
    {
        BudgetAgent::fake([
            ['allocations' => json_encode([])],
        ]);

        $agent = BudgetAgent::make();
        $response = $agent->prompt('Discretionary pool: $0. All income is allocated to locked/fixed.');

        $allocations = json_decode($response['allocations'], true);
        $this->assertEmpty($allocations);
    }

    public function test_instructions_contain_priority_rules(): void
    {
        $agent = BudgetAgent::make();
        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Emergency fund', $instructions);
        $this->assertStringContainsString('Essential variable', $instructions);
        $this->assertStringContainsString('Every dollar must be allocated', $instructions);
    }
}
