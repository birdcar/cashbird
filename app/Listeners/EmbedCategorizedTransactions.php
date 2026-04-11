<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionsCategorized;
use App\Jobs\EmbedTransactions;

class EmbedCategorizedTransactions
{
    public function handle(TransactionsCategorized $event): void
    {
        EmbedTransactions::dispatch($event->account->user);
    }
}
