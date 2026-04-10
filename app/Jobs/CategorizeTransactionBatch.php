<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CategorizationAgent;
use App\Events\TransactionsCategorized;
use App\Models\CategoryOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Categorization\CategoryResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CategorizeTransactionBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
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

        $overrideLookup = CategoryOverride::where('user_id', $this->user->id)
            ->pluck('category_id', 'merchant_name');

        /** @var Collection<int, Transaction> $needsAi */
        $needsAi = collect();

        foreach ($transactions as $transaction) {
            if ($transaction->merchant_name && $merchantCache->has($transaction->merchant_name)) {
                $transaction->update([
                    'category_id' => $merchantCache[$transaction->merchant_name],
                    'categorized_at' => now(),
                ]);

                continue;
            }

            if ($transaction->merchant_name && $overrideLookup->has($transaction->merchant_name)) {
                $transaction->update([
                    'category_id' => $overrideLookup[$transaction->merchant_name],
                    'categorized_at' => now(),
                ]);

                continue;
            }

            $needsAi->push($transaction);
        }

        // Batch remaining through AI
        if ($needsAi->isNotEmpty()) {
            $agent = CategorizationAgent::make()
                ->withCategoryTree($categoryTree)
                ->withOverrides($overrides->toArray());

            foreach ($needsAi as $transaction) {
                $prompt = implode("\n", [
                    'Merchant: '.($transaction->merchant_name ?? '(not available)'),
                    'Description: '.$transaction->description,
                    'Amount: '.($transaction->amount < 0 ? '-' : '+').'$'.number_format(abs($transaction->amount) / 100, 2),
                    'Type: '.($transaction->amount < 0 ? 'debit' : 'credit'),
                ]);

                $response = $agent->prompt($prompt);
                $categoryPath = (string) ($response['category_path'] ?? 'Uncategorized');
                $confidence = (string) ($response['confidence'] ?? 'low');
                $category = $resolver->resolve($categoryPath);

                if ($category === null) {
                    Log::warning("Unresolvable category path from AI: {$categoryPath}", [
                        'transaction_id' => $transaction->id,
                        'merchant' => $transaction->merchant_name,
                        'confidence' => $confidence,
                    ]);
                }

                $transaction->update([
                    'category_id' => $category?->id,
                    'categorized_at' => now(),
                ]);
            }
        }

        // Dispatch event per affected account
        $transactions->load('account');
        $transactions->pluck('account_id')->unique()->each(function ($accountId) use ($transactions) {
            $account = $transactions->first(fn ($t) => $t->account_id === $accountId)?->account;
            if ($account) {
                TransactionsCategorized::dispatch($account, $transactions->where('account_id', $accountId)->count());
            }
        });

        // If we fetched a full batch, there may be more
        if ($transactions->count() === 20) {
            static::dispatch($this->user);
        }
    }
}
