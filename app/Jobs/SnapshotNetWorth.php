<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\Services\NetWorthCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SnapshotNetWorth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function handle(NetWorthCalculator $calculator): void
    {
        User::chunkById(100, function ($users) use ($calculator) {
            foreach ($users as $user) {
                $data = $calculator->compute($user->id);

                NetWorthSnapshot::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'month' => now()->startOfMonth(),
                    ],
                    [
                        'total_assets' => $data['total_assets'],
                        'total_debts' => $data['total_debts'],
                        'net_worth' => $data['net_worth'],
                        'breakdown' => $data['breakdown'],
                    ]
                );
            }
        });
    }
}
