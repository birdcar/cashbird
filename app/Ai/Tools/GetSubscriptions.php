<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\RecurringCharge;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSubscriptions implements Tool
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function description(): Stringable|string
    {
        return 'List all detected recurring charges and subscriptions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $charges = RecurringCharge::where('user_id', $this->userId)
            ->where('is_active', true)
            ->with('category')
            ->get();

        return $charges->map(fn ($c) => [
            'merchant' => $c->merchant_name,
            'amount' => '$'.Money::format($c->average_amount),
            'frequency' => $c->frequency,
            'category' => $c->category?->name ?? 'Uncategorized',
        ])->toJson();
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
