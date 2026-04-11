<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\GetBudgetStatus;
use App\Ai\Tools\GetDebtOverview;
use App\Ai\Tools\GetSpendingTrends;
use App\Ai\Tools\GetSubscriptions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[UseSmartestModel]
#[MaxSteps(8)]
class InsightsAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        private readonly int $userId,
    ) {}

    public function instructions(): Stringable|string
    {
        $today = now()->format('Y-m-d');

        return <<<PROMPT
Analyze the user's financial data and identify actionable insights.

Today's date is {$today}. Use this to calculate relative timeframes like "less than 3 months to payoff."

Use the available tools to gather spending trends, subscription data, budget status, and debt information.

Each insight should be:
- Specific (name the merchant, category, or amount)
- Actionable (what should the user do?)
- Quantified (how much money is involved?)

Insight types:
- unused_subscription: Recurring charge for a service that may not be needed
- spending_spike: Category spending significantly above recent average
- savings_opportunity: Category consistently under budget (reallocation potential)
- debt_milestone: Approaching payoff on a debt (<\$500 remaining or <3 months)
- anomaly: Unusual transaction (much larger or smaller than typical for that merchant)

Severity levels:
- info: Informational, no action needed
- warning: Worth reviewing
- action_required: Should address soon

Return between 0 and 5 insights. Quality over quantity — only surface genuinely useful observations.
If data is insufficient to generate any insights, return an empty array.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new GetSubscriptions($this->userId),
            new GetSpendingTrends($this->userId),
            new GetBudgetStatus($this->userId),
            new GetDebtOverview($this->userId),
        ];
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'insights' => $schema->array()->required()
                ->items($schema->object([
                    'type' => $schema->string()->required()
                        ->enum(['unused_subscription', 'spending_spike', 'savings_opportunity', 'debt_milestone', 'anomaly'])
                        ->description('Insight type identifier'),
                    'title' => $schema->string()->required()
                        ->description('Short, descriptive title'),
                    'description' => $schema->string()->required()
                        ->description('Detailed explanation with specific amounts'),
                    'severity' => $schema->string()->required()
                        ->enum(['info', 'warning', 'action_required'])
                        ->description('How urgent this insight is'),
                    'data' => $schema->object([
                        'amount_cents' => $schema->integer()
                            ->description('Primary dollar amount in cents, if applicable'),
                        'merchant' => $schema->string()
                            ->description('Merchant name, if applicable'),
                        'category' => $schema->string()
                            ->description('Category name, if applicable'),
                        'monthly_amount_cents' => $schema->integer()
                            ->description('Monthly recurring amount in cents, if applicable'),
                    ])->required()
                        ->description('Supporting data keyed by context: amount_cents, merchant, category, monthly_amount_cents'),
                ]))
                ->description('List of financial insights'),
        ];
    }
}
