<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AskFinancialQuestionTool;
use App\Mcp\Tools\GetBudgetTool;
use App\Mcp\Tools\GetDebtStatusTool;
use App\Mcp\Tools\GetInsightsTool;
use App\Mcp\Tools\GetReportTool;
use App\Mcp\Tools\QueryTransactionsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Cashbird')]
#[Version('1.0.0')]
#[Instructions('Cashbird personal finance server. Query transactions, budgets, debts, reports, and insights. Ask financial questions in natural language.')]
class CashbirdServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        QueryTransactionsTool::class,
        GetBudgetTool::class,
        GetDebtStatusTool::class,
        AskFinancialQuestionTool::class,
        GetReportTool::class,
        GetInsightsTool::class,
    ];
}
