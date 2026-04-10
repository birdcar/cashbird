<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseSmartestModel]
class BudgetAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private int $discretionaryPool = 0;

    /** @var array<string, int> category_id => average_amount_cents */
    private array $historicalSpending = [];

    /** @var array<string, string> category_id => category_name */
    private array $categories = [];

    public function withDiscretionaryPool(int $cents): static
    {
        $this->discretionaryPool = $cents;

        return $this;
    }

    /** @param array<string, int> $spending category_id => average_amount_cents */
    public function withHistoricalSpending(array $spending): static
    {
        $this->historicalSpending = $spending;

        return $this;
    }

    /** @param array<string, string> $categories category_id => category_name */
    public function withCategories(array $categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $instructions = <<<'PROMPT'
You are a personal finance budget advisor. Given a user's financial context, allocate a discretionary spending pool across categories.

ALLOCATION PRIORITIES (in order)
1. Essential variable expenses first — groceries, gas, healthcare, transportation. Base on historical averages, scale down proportionally if pool is insufficient.
2. Lifestyle discretionary — dining, entertainment, shopping. Proportional to history, reduced to fit remaining pool.
3. Savings buffer — any remainder after priorities 1-2.

If the pool cannot cover all of priority 1, scale every priority-1 category equally until amounts sum to the pool.

RULES
- Every dollar must be allocated. Total of all amounts must equal the discretionary pool exactly.
- Return amounts in cents (integers). Do not return fractional cents.
- Never allocate negative amounts.
- If the pool is $0 or negative, return an empty array.
- Use only category IDs from the provided list.
- Rationale must be one sentence, max 100 characters.
PROMPT;

        if ($this->discretionaryPool > 0) {
            $poolDollars = number_format($this->discretionaryPool / 100, 2);
            $instructions .= "\n\nDISCRETIONARY POOL: {$this->discretionaryPool} cents (\${$poolDollars})";
        }

        if (! empty($this->categories)) {
            $instructions .= "\n\nAVAILABLE CATEGORIES (use these IDs only):\n";
            foreach ($this->categories as $id => $name) {
                $instructions .= "- {$id}: {$name}\n";
            }
        }

        if (! empty($this->historicalSpending)) {
            $instructions .= "\n\nHISTORICAL 3-MONTH SPENDING AVERAGES (cents/month):\n";
            foreach ($this->historicalSpending as $id => $amount) {
                $name = $this->categories[$id] ?? $id;
                $instructions .= "- {$name} ({$id}): {$amount} cents\n";
            }
        }

        return $instructions;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'allocations' => $schema->array()->required()
                ->items($schema->object([
                    'category_id' => $schema->string()->required()
                        ->description('Category UUID from the provided list.'),
                    'amount' => $schema->integer()->required()
                        ->description('Amount in cents. Must be >= 0.'),
                    'rationale' => $schema->string()->required()
                        ->description('One sentence, max 100 characters.'),
                ]))
                ->description('Budget allocations. Total of all amounts must equal the discretionary pool.'),
        ];
    }
}
