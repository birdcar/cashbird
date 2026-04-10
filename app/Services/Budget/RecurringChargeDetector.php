<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Models\RecurringCharge;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringChargeDetector
{
    /** @return Collection<int, RecurringCharge> */
    public function detect(int $userId, int $lookbackMonths = 6): Collection
    {
        $since = Carbon::now()->subMonths($lookbackMonths);

        $merchants = Transaction::where('user_id', $userId)
            ->where('amount', '<', 0)
            ->whereNotNull('merchant_name')
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('merchant_name, category_id, COUNT(*) as txn_count')
            ->groupBy('merchant_name', 'category_id')
            ->havingRaw('COUNT(*) >= 3')
            ->get();

        $results = collect();

        foreach ($merchants as $merchant) {
            $transactions = Transaction::where('user_id', $userId)
                ->where('merchant_name', $merchant->merchant_name)
                ->where('date', '>=', $since->toDateString())
                ->orderBy('date')
                ->get(['date', 'amount']);

            $pattern = $this->analyzePattern($merchant->merchant_name, $transactions);

            if ($pattern === null) {
                continue;
            }

            $charge = RecurringCharge::updateOrCreate(
                ['user_id' => $userId, 'merchant_name' => $merchant->merchant_name],
                [
                    'category_id' => $merchant->category_id,
                    'average_amount' => $pattern['average_amount'],
                    'frequency' => $pattern['frequency'],
                    'confidence' => $pattern['confidence'],
                    'last_seen_at' => $transactions->last()->date->toDateString(),
                    'is_active' => true,
                ],
            );

            $results->push($charge);
        }

        return $results;
    }

    /** @return array{average_amount: int, frequency: string, confidence: float}|null */
    public function analyzePattern(string $merchantName, Collection $transactions): ?array
    {
        if ($transactions->count() < 3) {
            return null;
        }

        $dates = $transactions->pluck('date')->map(fn ($d) => Carbon::parse($d));
        $amounts = $transactions->pluck('amount')->map(fn ($a) => abs((int) $a));

        $intervals = [];
        for ($i = 1; $i < $dates->count(); $i++) {
            $intervals[] = $dates[$i - 1]->diffInDays($dates[$i]);
        }

        if (empty($intervals)) {
            return null;
        }

        $meanInterval = array_sum($intervals) / count($intervals);
        $stdDev = $this->standardDeviation($intervals);

        if ($meanInterval < 1) {
            return null;
        }

        $confidence = round(1.0 - ($stdDev / $meanInterval), 2);
        $confidence = max(0.0, min(1.0, $confidence));

        if ($confidence < 0.7) {
            return null;
        }

        $frequency = match (true) {
            $meanInterval >= 25 && $meanInterval <= 35 => 'monthly',
            $meanInterval >= 80 && $meanInterval <= 100 => 'quarterly',
            $meanInterval >= 350 && $meanInterval <= 380 => 'annual',
            default => null,
        };

        if ($frequency === null) {
            return null;
        }

        return [
            'average_amount' => (int) round($amounts->avg()),
            'frequency' => $frequency,
            'confidence' => $confidence,
        ];
    }

    /** @param list<int|float> $values */
    private function standardDeviation(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiffs = 0.0;

        foreach ($values as $value) {
            $sumSquaredDiffs += ($value - $mean) ** 2;
        }

        return sqrt($sumSquaredDiffs / ($count - 1));
    }
}
