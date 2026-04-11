<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class CategoryClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @var array<int, array{category_id: string, category_path: string}> */
    private array $categories = [];

    /** @param array<int, array{category_id: string, category_path: string}> $categories */
    public function withCategories(array $categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $categoryList = collect($this->categories)
            ->map(fn ($c) => "- {$c['category_path']} (id: {$c['category_id']})")
            ->join("\n");

        return <<<PROMPT
You are classifying household spending categories into three buckets for a 50/30/20 budget framework.

CLASSIFICATION RULES:
- **Need**: Essential expenses the household cannot avoid — housing, utilities, groceries, transportation, insurance, minimum debt payments, healthcare, phone, internet.
- **Want**: Discretionary spending — dining out, entertainment, shopping, subscriptions, hobbies, gifts, clothing (beyond basics).
- **Savings**: Money set aside for the future — savings transfers, investment contributions, 401k, extra debt payments above minimums.

When uncertain, classify as "want" — it's safer to budget conservatively for discretionary spending than to overcount needs.

CATEGORIES TO CLASSIFY:
{$categoryList}

Return a JSON array of classifications for every category listed above.
PROMPT;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'classifications' => $schema->array()->required()
                ->items([
                    'category_id' => $schema->string()->required()
                        ->description('The UUID of the category'),
                    'classification' => $schema->string()->required()
                        ->enum(['need', 'want', 'savings'])
                        ->description('Budget classification'),
                    'rationale' => $schema->string()->required()
                        ->description('Brief reason for this classification'),
                ]),
        ];
    }
}
