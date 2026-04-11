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
#[MaxSteps(8)]
class ReportAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly int $userId,
        private readonly ?string $reportMonth = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $today = now()->format('Y-m-d');
        $month = $this->reportMonth ?? now()->subMonth()->format('F Y');

        return <<<PROMPT
Generate a monthly financial report for {$month} in markdown.

Today's date is {$today}. The report covers {$month} specifically — use tool data for that period.

Structure:

## Monthly Summary
One paragraph: total income, total spending, net savings/deficit, % of income saved.

## Category Breakdown
Top 10 categories by spending. For each: amount, % of total, vs last month change.

## Notable Changes
Significant month-over-month changes (>20% swing in any category). Explain likely causes.

## Budget Adherence
Categories over/under budget. Overall budget score (% of categories within allocation).

## Debt Progress
Balance changes, payments made, projected payoff date updates. If no debt data, write "No debt data available for this period."

## Recommendations
2-3 specific, actionable suggestions based on this month's data.

Use plain language. Be direct about problems. Reference specific dollar amounts.
If data is insufficient for a section, write "No data available for this period" rather than omitting it.
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
