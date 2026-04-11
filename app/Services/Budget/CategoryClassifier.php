<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Enums\BudgetCategory;
use App\Models\Category;
use App\Models\CategoryClassification;

class CategoryClassifier
{
    /**
     * @return array<string, BudgetCategory> category_id => classification
     */
    public function classifyForUser(int $userId): array
    {
        $existing = CategoryClassification::where('user_id', $userId)
            ->pluck('classification', 'category_id')
            ->map(fn ($v) => $v instanceof BudgetCategory ? $v : BudgetCategory::from($v))
            ->toArray();

        $allCategories = Category::whereNotNull('parent_id')->with('parent')->get();
        $result = [];

        foreach ($allCategories as $category) {
            $catId = $category->id;

            if (isset($existing[$catId])) {
                $result[$catId] = $existing[$catId];

                continue;
            }

            $classification = $this->defaultClassification($category);
            $result[$catId] = $classification;

            CategoryClassification::create([
                'user_id' => $userId,
                'category_id' => $catId,
                'classification' => $classification,
                'is_ai_assigned' => true,
            ]);
        }

        return $result;
    }

    public function overrideClassification(int $userId, string $categoryId, BudgetCategory $classification): void
    {
        CategoryClassification::updateOrCreate(
            ['user_id' => $userId, 'category_id' => $categoryId],
            ['classification' => $classification, 'is_ai_assigned' => false],
        );
    }

    public function getClassification(int $userId, string $categoryId): BudgetCategory
    {
        $record = CategoryClassification::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->first();

        if ($record) {
            return $record->classification;
        }

        $category = Category::with('parent')->find($categoryId);

        return $category ? $this->defaultClassification($category) : BudgetCategory::Want;
    }

    private function defaultClassification(Category $category): BudgetCategory
    {
        $parentName = $category->parent?->name ?? $category->name;

        $needsParents = [
            'Housing', 'Transportation', 'Health',
            'Fees & Charges', 'Debt Payments',
        ];

        $savingsParents = [
            'Savings & Investments',
        ];

        $needsChildren = [
            'Groceries', 'Insurance Premium', 'Phone', 'Internet',
        ];

        if (in_array($parentName, $savingsParents, true)) {
            return BudgetCategory::Savings;
        }

        if (in_array($parentName, $needsParents, true)) {
            return BudgetCategory::Need;
        }

        if (in_array($category->name, $needsChildren, true)) {
            return BudgetCategory::Need;
        }

        return BudgetCategory::Want;
    }
}
