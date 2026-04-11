<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\GetBudgetStatus;
use App\Ai\Tools\GetDebtOverview;
use App\Ai\Tools\GetSpendingSummary;
use App\Ai\Tools\QueryTransactions;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[UseSmartestModel]
#[MaxSteps(5)]
class QueryAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly int $userId,
    ) {}

    public function instructions(): Stringable|string
    {
        $today = now()->format('Y-m-d');

        return <<<PROMPT
You are a financial data assistant. Answer the user's question about their finances by querying their transaction, budget, and debt data using the available tools.

Today's date is {$today}. Use this to interpret relative time references like "last month", "this year", etc.

Rules:
- Always include specific dollar amounts in your answers. Format as \$X,XXX.XX.
- If the question is ambiguous, state your interpretation before answering.
- If data is insufficient to answer, say so clearly rather than guessing.
- Keep answers concise — 2-4 sentences for simple questions, more for complex analysis.
- When showing breakdowns, use a simple list format.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new QueryTransactions($this->userId),
            new GetSpendingSummary($this->userId),
            new GetBudgetStatus($this->userId),
            new GetDebtOverview($this->userId),
        ];
    }
}
