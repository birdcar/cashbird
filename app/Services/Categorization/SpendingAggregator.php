<?php

declare(strict_types=1);

namespace App\Services\Categorization;

use App\Models\SpendingAggregation;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SpendingAggregator
{
    /** @return array<string, mixed> */
    public function forPeriod(int $userId, string $periodType, Carbon $start, Carbon $end): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $cached = SpendingAggregation::where('user_id', $userId)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $startDate)
            ->whereDate('period_end', $endDate)
            ->get();

        if ($cached->isNotEmpty()) {
            return $cached->map(fn (SpendingAggregation $a) => [
                'category_id' => $a->category_id,
                'total_amount' => $a->total_amount,
                'transaction_count' => $a->transaction_count,
            ])->all();
        }

        return $this->computeAndCache($userId, $periodType, $start, $end);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function topCategories(int $userId, Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return Transaction::where('user_id', $userId)
            ->whereNotNull('category_id')
            ->where('amount', '<', 0)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('category_id, SUM(ABS(amount)) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('category_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->with('category')
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'category_name' => $row->category?->name,
                'total_amount' => (int) $row->total_amount,
                'transaction_count' => (int) $row->transaction_count,
            ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function monthOverMonth(int $userId, int $months = 6): array
    {
        $now = Carbon::now()->startOfMonth();
        $rangeStart = $now->copy()->subMonths($months - 1);
        $rangeEnd = $now->copy()->endOfMonth();

        $rows = Transaction::where('user_id', $userId)
            ->where('amount', '<', 0)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->selectRaw("strftime('%Y-%m', date) as month, SUM(amount) as total_amount")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->pluck('total_amount', 'month');

        $results = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthKey = $now->copy()->subMonths($i)->format('Y-m');
            $results[] = [
                'month' => $monthKey,
                'total_amount' => (int) abs((int) ($rows[$monthKey] ?? 0)),
            ];
        }

        return $results;
    }

    public function invalidateCache(int $userId, Carbon $month): void
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        SpendingAggregation::where('user_id', $userId)
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_end', '<=', $end->toDateString())
            ->delete();
    }

    /** @return array<int, array<string, mixed>> */
    private function computeAndCache(int $userId, string $periodType, Carbon $start, Carbon $end): array
    {
        $rows = Transaction::where('user_id', $userId)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('category_id, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('category_id')
            ->get();

        $results = [];
        foreach ($rows as $row) {
            SpendingAggregation::updateOrCreate(
                [
                    'user_id' => $userId,
                    'category_id' => $row->category_id,
                    'period_type' => $periodType,
                    'period_start' => $start->toDateString(),
                ],
                [
                    'period_end' => $end->toDateString(),
                    'total_amount' => (int) $row->total_amount,
                    'transaction_count' => (int) $row->transaction_count,
                ],
            );

            $results[] = [
                'category_id' => $row->category_id,
                'total_amount' => (int) $row->total_amount,
                'transaction_count' => (int) $row->transaction_count,
            ];
        }

        return $results;
    }
}
