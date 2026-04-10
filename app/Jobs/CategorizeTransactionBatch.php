<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CategorizationAgent;
use App\Events\TransactionsCategorized;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Categorization\CategoryResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CategorizeTransactionBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(CategoryResolver $resolver): void
    {
        $transactions = Transaction::where('user_id', $this->user->id)
            ->whereNull('category_id')
            ->whereNull('categorized_at')
            ->limit(20)
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $overrides = $resolver->getOverridesMap($this->user->id);
        $merchantCache = $resolver->getMerchantCache($this->user->id);
        $categoryTree = CategorizationAgent::buildCategoryTree();

        $needsAi = collect();

        foreach ($transactions as $transaction) {
            // Check merchant cache first
            if ($transaction->merchant_name && $merchantCache->has($transaction->merchant_name)) {
                $transaction->update([
                    'category_id' => $merchantCache[$transaction->merchant_name],
                    'categorized_at' => now(),
                ]);
                continue;
            }

            // Check user overrides
            if ($transaction->merchant_name) {
                $override = $resolver->getOverridesForMerchant($transaction->merchant_name, $this->user->id);
                if ($override) {
                    $transaction->update([
                        'category_id' => $override->id,
                        'categorized_at' => now(),
                    ]);
                    continue;
                }
            }

            $needsAi->push($transaction);
        }

        // Batch remaining through AI
        if ($needsAi->isNotEmpty()) {
            $agent = CategorizationAgent::make()
                ->withCategoryTree($categoryTree)
                ->withOverrides($overrides->toArray());

            foreach ($needsAi as $transaction) {
                $prompt = sprintf(
                    'Categorize: %s, description: %s, amount: $%.2f',
                    $transaction->merchant_name ?? 'Unknown',
                    $transaction->description,
                    abs($transaction->amount) / 100,
                );

                $response = $agent->prompt($prompt);
                $categoryPath = $response['category_path'] ?? 'Uncategorized';
                $category = $resolver->resolve($categoryPath);

                $transaction->update([
                    'category_id' => $category?->id,
                    'categorized_at' => now(),
                ]);
            }
        }

        // Dispatch event per affected account
        $transactions->pluck('account_id')->unique()->each(function ($accountId) use ($transactions) {
            $account = $transactions->first(fn ($t) => $t->account_id === $accountId)?->account;
            if ($account) {
                TransactionsCategorized::dispatch($account, $transactions->where('account_id', $accountId)->count());
            }
        });

        // If more uncategorized exist, dispatch another batch
        $remaining = Transaction::where('user_id', $this->user->id)
            ->whereNull('category_id')
            ->whereNull('categorized_at')
            ->exists();

        if ($remaining) {
            static::dispatch($this->user);
        }
    }
}
