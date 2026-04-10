<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
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
You are a personal finance transaction categorizer. Your sole job is to assign each bank transaction to exactly one category from the category tree below.

OUTPUT FORMAT
Return a JSON object with:
- category_path: "Parent > Child" (e.g., "Food & Drink > Groceries") or just "Parent" for top-level (e.g., "Uncategorized")
- confidence: "high", "medium", or "low"
Never invent category names. Use only names that appear in the tree exactly as written.

CLASSIFICATION RULES
1. Merchant name is the primary signal. If the merchant appears in the user examples below, use that category unconditionally.
2. Description is used when merchant name is absent or ambiguous.
3. Amount sign: negative = debit/expense, positive = credit/income. Positive amounts nearly always belong under "Income" unless context says otherwise.
4. Transfers between accounts (merchant contains TRANSFER, ZELLE, VENMO, ACH, etc.) → "Transfers > Internal Transfer" or "Transfers > Peer Payment".
5. Refunds (positive amount, description contains REFUND, RETURN, CREDIT) → "Income > Refunds".
6. When genuinely ambiguous, prefer the narrowest defensible category over a broad parent.
7. Only return "Uncategorized" when no category in the tree is a reasonable fit.
PROMPT;

        if ($this->categoryTree !== '') {
            $instructions .= "\n\nCATEGORY TREE (valid paths):\n".$this->categoryTree;
        }

        if (! empty($this->overrideExamples)) {
            $instructions .= "\n\nEXAMPLE CATEGORIZATIONS (user preferences — use to calibrate):\n";
            foreach ($this->overrideExamples as $merchant => $category) {
                $instructions .= "Merchant: {$merchant} → {$category}\n";
            }
        }

        return $instructions;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_path' => $schema->string()->required()
                ->description('Full category path like "Food & Drink > Groceries". Use "Uncategorized" if no match.'),
            'confidence' => $schema->string()->required()
                ->description('Classification confidence: "high" = obvious match, "medium" = reasonable inference, "low" = best guess'),
        ];
    }

    public static function buildCategoryTree(): string
    {
        $parents = Category::whereNull('parent_id')->with('children')->orderBy('name')->get();
        $lines = [];

        foreach ($parents as $parent) {
            if ($parent->children->isEmpty()) {
                $lines[] = $parent->name;
            } else {
                foreach ($parent->children as $child) {
                    $lines[] = "{$parent->name} > {$child->name}";
                }
            }
        }

        return implode("\n", $lines);
    }
}
