<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SemanticSearchTransactions implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Search transactions by meaning. Use this when the user asks about a type of spending (e.g., "coffee shops", "eating out", "subscriptions") rather than a specific merchant name or category. Returns transactions semantically similar to the search query.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! DB::connection() instanceof PostgresConnection) {
            return 'Semantic search is not available (requires PostgreSQL with pgvector).';
        }

        $query = (string) $request['query'];

        $transactions = Transaction::where('user_id', $this->userId)
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.4)
            ->with('category')
            ->limit((int) ($request['limit'] ?? 25))
            ->get();

        if ($transactions->isEmpty()) {
            return 'No semantically similar transactions found.';
        }

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
            'query' => $schema->string()->required()
                ->description('Natural language description of the kind of transactions to find (e.g., "coffee shops", "grocery stores", "streaming subscriptions")'),
            'limit' => $schema->integer()
                ->description('Max results to return (default 25)'),
        ];
    }
}
