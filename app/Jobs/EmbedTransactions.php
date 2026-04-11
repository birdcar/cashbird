<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Embeddings;

class EmbedTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        $transactions = Transaction::where('user_id', $this->user->id)
            ->whereNull('embedding')
            ->whereNotNull('categorized_at')
            ->limit(50)
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $texts = $transactions->map(fn (Transaction $t) => implode(' | ', array_filter([
            $t->merchant_name,
            $t->description,
            $t->category?->name,
            $t->amount < 0 ? 'expense' : 'income',
        ])))->values()->all();

        $response = Embeddings::for($texts)->generate();

        foreach ($transactions->values() as $i => $transaction) {
            $transaction->update(['embedding' => $response->embeddings[$i]]);
        }

        $remaining = Transaction::where('user_id', $this->user->id)
            ->whereNull('embedding')
            ->whereNotNull('categorized_at')
            ->exists();

        if ($remaining) {
            static::dispatch($this->user);
        }
    }
}
