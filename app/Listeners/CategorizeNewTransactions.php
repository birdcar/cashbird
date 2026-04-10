<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionsSynced;
use App\Jobs\CategorizeTransactionBatch;

class CategorizeNewTransactions
{
    public function handle(TransactionsSynced $event): void
    {
        CategorizeTransactionBatch::dispatch($event->account->user);
    }
}
