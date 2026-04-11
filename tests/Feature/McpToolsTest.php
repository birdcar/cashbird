<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\QueryAgent;
use App\Enums\InsightStatus;
use App\Mcp\Servers\CashbirdServer;
use App\Mcp\Tools\AskFinancialQuestionTool;
use App\Mcp\Tools\GetBudgetTool;
use App\Mcp\Tools\GetDebtStatusTool;
use App\Mcp\Tools\GetInsightsTool;
use App\Mcp\Tools\GetReportTool;
use App\Mcp\Tools\QueryTransactionsTool;
use App\Models\Debt;
use App\Models\Insight;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_query_transactions_returns_results(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'merchant_name' => 'GROCERY STORE',
            'amount' => -5000,
            'date' => now(),
        ]);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(QueryTransactionsTool::class, [
                'merchant' => 'GROCERY',
            ]);

        $response->assertOk()
            ->assertSee('GROCERY STORE');
    }

    public function test_get_budget_with_no_budget(): void
    {
        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetBudgetTool::class);

        $response->assertOk()
            ->assertSee('No budget period found');
    }

    public function test_get_debt_status_with_no_debts(): void
    {
        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetDebtStatusTool::class);

        $response->assertOk()
            ->assertSee('No active debts');
    }

    public function test_get_debt_status_with_debts(): void
    {
        Debt::factory()->creditCard()->create([
            'user_id' => $this->user->id,
            'name' => 'Chase Sapphire',
            'current_balance' => 500000,
            'minimum_payment' => 10000,
        ]);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetDebtStatusTool::class);

        $response->assertOk()
            ->assertSee('Chase Sapphire');
    }

    public function test_ask_financial_question(): void
    {
        QueryAgent::fake(['You spent $450.00 on dining.']);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(AskFinancialQuestionTool::class, [
                'question' => 'How much did I spend on dining?',
            ]);

        $response->assertOk()
            ->assertSee('450');
    }

    public function test_get_report_with_no_reports(): void
    {
        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetReportTool::class);

        $response->assertOk()
            ->assertSee('No report found');
    }

    public function test_get_report_returns_content(): void
    {
        Report::factory()->create([
            'user_id' => $this->user->id,
            'period_month' => '2026-03-01',
            'content' => '## Monthly Summary\n\nTest report.',
        ]);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetReportTool::class, [
                'month' => '2026-03',
            ]);

        $response->assertOk()
            ->assertSee('Monthly Summary');
    }

    public function test_get_insights_active(): void
    {
        Insight::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Dining spending spike',
        ]);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetInsightsTool::class);

        $response->assertOk()
            ->assertSee('Dining spending spike');
    }

    public function test_get_insights_filters_by_status(): void
    {
        Insight::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Active insight',
            'status' => InsightStatus::Active,
        ]);
        Insight::factory()->dismissed()->create([
            'user_id' => $this->user->id,
            'title' => 'Dismissed insight',
        ]);

        $response = CashbirdServer::actingAs($this->user)
            ->tool(GetInsightsTool::class, ['status' => 'dismissed']);

        $response->assertOk()
            ->assertSee('Dismissed insight');
    }
}
