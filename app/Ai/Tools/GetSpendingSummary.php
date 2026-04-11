<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSpendingSummary implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Get spending aggregated by category for a given time period.';
    }

    public function handle(Request $request): Stringable|string
    {
        $period = $request['period'] ?? 'month';
        $start = match ($period) {
            'quarter' => Carbon::now()->subMonths(3)->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        $transactions = Transaction::where('user_id', $this->userId)
            ->where('amount', '<', 0)
            ->where('date', '>=', $start)
            ->with('category')
            ->get();

        $byCategory = $transactions->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group, $name) => [
                'category' => $name,
                'total' => '$'.Money::format(abs($group->sum('amount'))),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return json_encode([
            'period' => $period,
            'start' => $start->format('Y-m-d'),
            'end' => now()->format('Y-m-d'),
            'total_spending' => '$'.Money::format(abs($transactions->sum('amount'))),
            'categories' => $byCategory,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['month', 'quarter', 'year'])
                ->description('Time period: month (current), quarter (last 3 months), year (current year)'),
        ];
    }
}
