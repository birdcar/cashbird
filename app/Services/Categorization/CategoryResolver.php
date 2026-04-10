<?php

declare(strict_types=1);

namespace App\Services\Categorization;

use App\Models\Category;
use App\Models\CategoryOverride;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryResolver
{
    public function resolve(string $categoryPath): ?Category
    {
        $parts = array_map('trim', explode('>', $categoryPath));

        if (count($parts) === 1) {
            return Category::where('name', $parts[0])->whereNull('parent_id')->first();
        }

        $parent = Category::where('name', $parts[0])->whereNull('parent_id')->first();

        if (! $parent) {
            return null;
        }

        return Category::where('name', $parts[1])->where('parent_id', $parent->id)->first();
    }

    public function getOverridesForMerchant(string $merchantName, int $userId): ?Category
    {
        $override = CategoryOverride::where('user_id', $userId)
            ->where('merchant_name', $merchantName)
            ->first();

        return $override?->category;
    }

    public function saveOverride(Transaction $transaction, Category $category, int $userId): void
    {
        if (! $transaction->merchant_name) {
            return;
        }

        CategoryOverride::updateOrCreate(
            ['user_id' => $userId, 'merchant_name' => $transaction->merchant_name],
            ['category_id' => $category->id],
        );

        $this->invalidateMerchantCache($userId);
    }

    /** @return Collection<string, string> merchant_name => category_id */
    public function getOverridesMap(int $userId): Collection
    {
        return CategoryOverride::where('user_id', $userId)
            ->with('category.parent')
            ->get()
            ->mapWithKeys(fn (CategoryOverride $o) => [
                $o->merchant_name => $o->category->fullPath(),
            ]);
    }

    public function getMerchantCache(int $userId): Collection
    {
        $cacheKey = "merchant_categories:{$userId}";

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return Transaction::where('user_id', $userId)
                ->whereNotNull('merchant_name')
                ->whereNotNull('category_id')
                ->selectRaw('merchant_name, category_id, COUNT(*) as cnt')
                ->groupBy('merchant_name', 'category_id')
                ->havingRaw('COUNT(*) >= 3')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->merchant_name => $row->category_id]);
        });
    }

    private function invalidateMerchantCache(int $userId): void
    {
        Cache::forget("merchant_categories:{$userId}");
    }
}
