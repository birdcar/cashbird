<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionsCategorized;
use App\Jobs\UpdateReadyToSpend;

class UpdateReadyToSpendOnTransaction
{
    public function handle(TransactionsCategorized $event): void
    {
        UpdateReadyToSpend::dispatch($event->account->user_id);
    }
}
