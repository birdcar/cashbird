<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionsSynced;
use App\Services\Debt\DebtSynchronizer;

class SyncDebtsOnTransactionSync
{
    public function __construct(
        private readonly DebtSynchronizer $synchronizer,
    ) {}

    public function handle(TransactionsSynced $event): void
    {
        $this->synchronizer->syncAccount($event->account->user, $event->account);
    }
}
