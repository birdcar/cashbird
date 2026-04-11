<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\GetBudgetStatus;
use App\Ai\Tools\GetDebtOverview;
use App\Ai\Tools\GetSpendingSummary;
use App\Ai\Tools\GetSpendingTrends;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[UseSmartestModel]
#[MaxSteps(5)]
class ReportAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly int $userId,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Generate a monthly financial report in markdown. Structure:

## Monthly Summary
One paragraph: total income, total spending, net savings/deficit, % of income saved.

## Category Breakdown
Top 10 categories by spending. For each: amount, % of total, vs last month change.

## Notable Changes
Significant month-over-month changes (>20% swing in any category). Explain likely causes.

## Budget Adherence
Categories over/under budget. Overall budget score (% of categories within allocation).

## Debt Progress
Balance changes, payments made, projected payoff date updates. If no debt data, omit this section.

## Recommendations
2-3 specific, actionable suggestions based on this month's data.

Use plain language. Be direct about problems. Reference specific dollar amounts.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new GetSpendingSummary($this->userId),
            new GetSpendingTrends($this->userId),
            new GetBudgetStatus($this->userId),
            new GetDebtOverview($this->userId),
        ];
    }
}
