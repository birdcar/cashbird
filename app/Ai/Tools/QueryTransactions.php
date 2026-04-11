<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryTransactions implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Search and filter the user\'s financial transactions by date range, category, merchant, or amount.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Transaction::where('user_id', $this->userId)
            ->with('category');

        if ($request['date_start'] ?? null) {
            $query->where('date', '>=', $request['date_start']);
        }
        if ($request['date_end'] ?? null) {
            $query->where('date', '<=', $request['date_end']);
        }
        if ($request['category'] ?? null) {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', '%'.$request['category'].'%'));
        }
        if ($request['merchant'] ?? null) {
            $query->where('merchant_name', 'like', '%'.$request['merchant'].'%');
        }
        if ($request['min_amount'] ?? null) {
            $query->where('amount', '<=', -(int) ($request['min_amount'] * 100));
        }
        if ($request['max_amount'] ?? null) {
            $query->where('amount', '>=', -(int) ($request['max_amount'] * 100));
        }

        $transactions = $query->orderByDesc('date')->limit((int) ($request['limit'] ?? 50))->get();

        return $transactions->map(fn ($t) => [
            'date' => $t->date->format('Y-m-d'),
            'merchant' => $t->merchant_name,
            'description' => $t->description,
            'amount' => '$'.Money::format(abs($t->amount)),
            'type' => $t->amount < 0 ? 'expense' : 'income',
            'category' => $t->category?->name ?? 'Uncategorized',
        ])->toJson();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date_start' => $schema->string()->description('Start date (YYYY-MM-DD)'),
            'date_end' => $schema->string()->description('End date (YYYY-MM-DD)'),
            'category' => $schema->string()->description('Category name to filter by'),
            'merchant' => $schema->string()->description('Merchant name to search for'),
            'min_amount' => $schema->number()->description('Minimum amount in dollars'),
            'max_amount' => $schema->number()->description('Maximum amount in dollars'),
            'limit' => $schema->integer()->description('Max results to return (default 50)'),
        ];
    }
}
