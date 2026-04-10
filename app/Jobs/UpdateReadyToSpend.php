<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Budget\ReadyToSpend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateReadyToSpend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5];

    public function __construct(
        public int $userId,
    ) {}

    public function handle(ReadyToSpend $rts): void
    {
        $rts->publish($this->userId);
    }
}
