<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('claude-sonnet-4-5-20250514')]
class CategorizationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private string $categoryTree = '';

    /** @var array<string, string> */
    private array $overrideExamples = [];

    public function withCategoryTree(string $tree): static
    {
        $this->categoryTree = $tree;

        return $this;
    }

    /** @param array<string, string> $overrides merchant_name => "Parent > Child" */
    public function withOverrides(array $overrides): static
    {
        $this->overrideExamples = $overrides;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $instructions = <<<'PROMPT'
You are a financial transaction categorizer. Given a transaction, classify it
into the most specific category from the provided category tree.

Rules:
- Use merchant_name as primary signal, description as secondary
- If a user override exists for this merchant, always use that category
- For ambiguous transactions, prefer the most common category for that merchant
- Return the full category path: "Parent > Child"
- If truly unrecognizable, return "Uncategorized"
PROMPT;

        if ($this->categoryTree !== '') {
            $instructions .= "\n\nCategory Tree:\n" . $this->categoryTree;
        }

        if (! empty($this->overrideExamples)) {
            $instructions .= "\n\nUser overrides (always use these):\n";
            foreach ($this->overrideExamples as $merchant => $category) {
                $instructions .= "- {$merchant} → {$category}\n";
            }
        }

        return $instructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_path' => $schema->string()->required()->description('Full category path like "Food & Drink > Groceries"'),
        ];
    }

    public static function buildCategoryTree(): string
    {
        $parents = Category::whereNull('parent_id')->with('children')->orderBy('name')->get();
        $lines = [];

        foreach ($parents as $parent) {
            $children = $parent->children->pluck('name')->implode(', ');
            $lines[] = $children ? "{$parent->name}: {$children}" : $parent->name;
        }

        return implode("\n", $lines);
    }
}
