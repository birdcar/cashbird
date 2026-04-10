<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TransactionsCategorized;
use App\Events\TransactionsSynced;
use App\Listeners\CategorizeNewTransactions;
use App\Listeners\InvalidateSpendingCache;
use App\Listeners\SyncDebtsOnTransactionSync;
use App\Listeners\UpdateReadyToSpendOnTransaction;
use App\Services\Teller\TellerClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TellerClient::class, fn () => new TellerClient(
            certPath: config('teller.cert_path'),
            keyPath: config('teller.key_path'),
            baseUrl: config('teller.base_url', 'https://api.teller.io'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TransactionsSynced::class, CategorizeNewTransactions::class);
        Event::listen(TransactionsSynced::class, SyncDebtsOnTransactionSync::class);
        Event::listen(TransactionsCategorized::class, InvalidateSpendingCache::class);
        Event::listen(TransactionsCategorized::class, UpdateReadyToSpendOnTransaction::class);
    }
}
