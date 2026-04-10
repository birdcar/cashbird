<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Teller\TellerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAllAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function handle(TellerClient $teller): void
    {
        $enrollments = $this->user->enrollments()->where('status', 'active')->get();

        foreach ($enrollments as $enrollment) {
            $accessToken = $enrollment->getDecryptedAccessToken();

            $tellerAccounts = $teller->listAccounts($accessToken);

            foreach ($tellerAccounts as $tellerAccount) {
                $account = $this->user->accounts()->updateOrCreate(
                    ['teller_id' => $tellerAccount['id']],
                    [
                        'enrollment_id' => $enrollment->id,
                        'institution_id' => $enrollment->institution_id,
                        'name' => $tellerAccount['name'],
                        'type' => $tellerAccount['type'],
                        'subtype' => $tellerAccount['subtype'] ?? null,
                        'currency' => $tellerAccount['currency'] ?? 'USD',
                    ],
                );

                SyncAccountTransactions::dispatch($account, fullSync: true);
            }
        }
    }
}
