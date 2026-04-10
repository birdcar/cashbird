<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Account;
use Illuminate\Foundation\Events\Dispatchable;

class TransactionsCategorized
{
    use Dispatchable;

    public function __construct(
        public Account $account,
        public int $count,
    ) {}
}
