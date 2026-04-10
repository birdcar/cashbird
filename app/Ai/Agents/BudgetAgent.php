<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class BudgetAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a personal finance budget advisor. Given a user's financial context, allocate a discretionary spending pool across categories.

INPUT
You will receive: monthly income, locked obligations, fixed recurring charges, historical spending averages by category, and the discretionary pool available.

ALLOCATION PRIORITIES (in order)
1. Emergency fund contribution (if user has less than 3 months of expenses saved)
2. Essential variable expenses (groceries, gas, healthcare) — based on historical averages
3. Discretionary spending — proportional to historical patterns, reduced to fit the pool

RULES
- Every dollar must be allocated. The total of all allocations must equal the discretionary pool exactly.
- Return allocations as a JSON array of objects with: category_id, amount (in cents), rationale
- Never allocate negative amounts
- If the pool is $0, return an empty array
PROMPT;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'allocations' => $schema->string()->required()
                ->description('JSON array of {category_id, amount, rationale} objects. Total must equal discretionary pool.'),
        ];
    }
}
