<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('query_transactions')]
#[Description('Search and filter financial transactions by date range, category, merchant, or amount.')]
class QueryTransactionsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'date_start' => $schema->string()->description('Start date (YYYY-MM-DD)'),
            'date_end' => $schema->string()->description('End date (YYYY-MM-DD)'),
            'category' => $schema->string()->description('Category name to filter by'),
            'merchant' => $schema->string()->description('Merchant name to search'),
            'min_amount' => $schema->number()->description('Minimum dollar amount'),
            'max_amount' => $schema->number()->description('Maximum dollar amount'),
            'limit' => $schema->integer()->description('Max results (default 50)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return Response::error('Authentication required.');
        }

        $query = Transaction::where('user_id', $user->id)->with('category');

        if ($request->get('date_start')) {
            $query->where('date', '>=', $request->get('date_start'));
        }
        if ($request->get('date_end')) {
            $query->where('date', '<=', $request->get('date_end'));
        }
        if ($request->get('category')) {
            $query->whereHas('category', fn ($q) => $q->where('name', 'like', '%'.$request->get('category').'%'));
        }
        if ($request->get('merchant')) {
            $query->where('merchant_name', 'like', '%'.$request->get('merchant').'%');
        }
        if ($request->get('min_amount')) {
            $query->where('amount', '<=', -(int) ($request->get('min_amount') * 100));
        }
        if ($request->get('max_amount')) {
            $query->where('amount', '>=', -(int) ($request->get('max_amount') * 100));
        }

        $transactions = $query->orderByDesc('date')
            ->limit((int) ($request->get('limit', 50)))
            ->get();

        $result = $transactions->map(fn ($t) => [
            'date' => $t->date->format('Y-m-d'),
            'merchant' => $t->merchant_name,
            'description' => $t->description,
            'amount' => '$'.Money::format(abs($t->amount)),
            'type' => $t->amount < 0 ? 'expense' : 'income',
            'category' => $t->category?->name ?? 'Uncategorized',
        ]);

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }
}
