<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionsCategorized;
use App\Services\Categorization\SpendingAggregator;
use Carbon\Carbon;

class InvalidateSpendingCache
{
    public function __construct(
        private readonly SpendingAggregator $aggregator,
    ) {}

    public function handle(TransactionsCategorized $event): void
    {
        $this->aggregator->invalidateCache(
            $event->account->user_id,
            Carbon::now(),
        );
    }
}
