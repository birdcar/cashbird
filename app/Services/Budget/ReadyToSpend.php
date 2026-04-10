<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReadyToSpend
{
    /**
     * @return array<string, array{allocated: int, spent: int, pending: int, remaining: int, daily_safe: int}>
     */
    public function compute(int $userId, ?string $categoryId = null): array
    {
        $user = User::findOrFail($userId);
        $period = $user->currentBudgetPeriod();

        if (! $period) {
            return [];
        }

        $allocations = $period->allocations()->with('category')->get();
        $now = Carbon::now();
        $endOfMonth = $now->copy()->endOfMonth();
        $daysRemaining = max(1, (int) $now->diffInDays($endOfMonth) + 1);

        $results = [];

        foreach ($allocations as $allocation) {
            if ($categoryId !== null && $allocation->category_id !== $categoryId) {
                continue;
            }

            $posted = (int) abs(Transaction::where('user_id', $userId)
                ->where('category_id', $allocation->category_id)
                ->where('amount', '<', 0)
                ->where('status', 'posted')
                ->where('date', '>=', $period->month->toDateString())
                ->where('date', '<=', $endOfMonth->toDateString())
                ->sum('amount'));

            $pending = (int) abs(Transaction::where('user_id', $userId)
                ->where('category_id', $allocation->category_id)
                ->where('amount', '<', 0)
                ->where('status', 'pending')
                ->where('date', '>=', $period->month->toDateString())
                ->where('date', '<=', $endOfMonth->toDateString())
                ->sum('amount'));

            $remaining = $allocation->allocated_amount - $posted - $pending;
            $dailySafe = (int) floor($remaining / $daysRemaining);

            $results[$allocation->category_id] = [
                'allocated' => $allocation->allocated_amount,
                'spent' => $posted,
                'pending' => $pending,
                'remaining' => $remaining,
                'daily_safe' => $dailySafe,
            ];
        }

        return $results;
    }

    public function publish(int $userId): void
    {
        $data = $this->compute($userId);
        $cacheKey = "cashbird:rts:{$userId}";

        Cache::put($cacheKey, $data, 300);
    }

    public function dailySafeToSpend(int $userId, string $categoryId): int
    {
        $data = $this->compute($userId, $categoryId);

        return $data[$categoryId]['daily_safe'] ?? 0;
    }
}
